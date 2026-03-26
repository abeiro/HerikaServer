<?php
/**
 * Relationship Dynamics — Core Class
 *
 * Organic relationship mechanics: love languages, diminishing returns,
 * passion (RPM→Speed), reunion spikes, jealousy, repair cycles, stages.
 *
 * Architecture: CHIM + Sharmat drive, MARAS rides along.
 * Works without MARAS — degrades gracefully.
 *
 * All state in nsfw_npc_data.extended_data.relationship_dynamics (JSONB).
 * Config in conf_opts key 'relationship_dynamics_config'.
 */

class RelationshipDynamics
{
    private static $config = null;
    private static $npcCache = [];

    // Love language types
    const LL_WORDS   = 'words_of_affirmation';
    const LL_TIME    = 'quality_time';
    const LL_TOUCH   = 'physical_touch';
    const LL_SERVICE = 'acts_of_service';
    const LL_GIFTS   = 'gifts';

    // Warmth curve presets
    const CURVE_SLOW_BURN   = 'slow_burn';
    const CURVE_MODERATE    = 'moderate';
    const CURVE_QUICK       = 'quick_warmth';
    const CURVE_GUARDED     = 'guarded';

    // Relationship stages
    const STAGE_EARLY       = 'early';
    const STAGE_ESTABLISHED = 'established';
    const STAGE_DEEP        = 'deep';

    // Warmth curve parameters: [decay_rate, half_life_hours, lambda, passion_decay_per_hour]
    const CURVE_PARAMS = [
        'slow_burn'    => ['decay_rate' => 0.10, 'half_life' => 10.0, 'lambda' => 0.069, 'passion_decay' => 2.5],
        'moderate'     => ['decay_rate' => 0.08, 'half_life' =>  8.0, 'lambda' => 0.087, 'passion_decay' => 3.0],
        'quick_warmth' => ['decay_rate' => 0.06, 'half_life' =>  6.0, 'lambda' => 0.116, 'passion_decay' => 4.0],
        'guarded'      => ['decay_rate' => 0.12, 'half_life' => 12.0, 'lambda' => 0.058, 'passion_decay' => 5.0],
    ];

    // Stage properties: [passion_floor, passion_ceiling, gain_mult, dr_rate_mult]
    const STAGE_PARAMS = [
        'early'       => ['floor' => 0,  'ceiling' => 100, 'gain_mult' => 1.3, 'dr_mult' => 0.8],
        'established' => ['floor' => 5,  'ceiling' => 70,  'gain_mult' => 1.0, 'dr_mult' => 1.2],
        'deep'        => ['floor' => 15, 'ceiling' => 50,  'gain_mult' => 0.8, 'dr_mult' => 1.0],
    ];

    // Temperament → passion gain multiplier (used when MARAS or inferred temperament available)
    const TEMPERAMENT_PASSION_MULT = [
        'Romantic'    => 1.3,
        'Anxious'     => 1.2,
        'Playful'     => 1.15,
        'Humble'      => 1.1,
        'Nurturing'   => 1.05,
        'Gentle'      => 1.0,
        'Jealous'     => 1.0,
        'Stoic'       => 0.85,
        'Proud'       => 0.8,
        'Bold'        => 0.75,
        'Independent' => 0.7,
        'Defiant'     => 0.7,
        'Guarded'     => 0.6,
    ];

    // Temperament -> bleedout passion drain (negative: how much passion is lost when NPC falls)
    // Guarded/Stoic NPCs lose more (they see vulnerability as weakness)
    // Nurturing/Anxious NPCs lose less (they bond through shared danger)
    const TEMPERAMENT_BLEEDOUT_DRAIN = [
        'Anxious'     => -3.0,  // Spirals into panic, abandonment terror
        'Guarded'     => -2.5,  // Walls slam up instantly
        'Independent' => -2.0,  // Vulnerability is intolerable
        'Proud'       => -2.0,  // Humiliation of helplessness
        'Jealous'     => -1.5,  // Fear of being replaced while weak
        'Gentle'      => -1.5,  // Deeply shaken by violence
        'Romantic'    => -1.0,  // Scared but trusts their partner
        'Nurturing'   => -1.0,  // Worried about others, not self
        'Humble'      => -0.8,  // Accepts it quietly
        'Playful'     => -0.5,  // Shakes it off with humor
        'Stoic'       => -0.5,  // Barely registers externally
        'Bold'        => -0.3,  // Rage fuel, not fear
        'Defiant'     =>  1.0,  // Fights HARDER when cornered — passion UP
    ];


    // Temperament → reunion multiplier
    const TEMPERAMENT_REUNION_MULT = [
        'Romantic'    => 1.5,
        'Anxious'     => 1.4,
        'Jealous'     => 1.2,
        'Playful'     => 1.1,
        'Humble'      => 1.0,
        'Nurturing'   => 1.0,
        'Gentle'      => 0.9,
        'Proud'       => 0.8,
        'Stoic'       => 0.7,
        'Bold'        => 0.6,
        'Guarded'     => 0.6,
        'Independent' => 0.5,
        'Defiant'     => 0.5,
    ];

    // Temperament → jealousy multiplier
    const TEMPERAMENT_JEALOUSY_MULT = [
        'Anxious'     => 2.5,
        'Jealous'     => 2.0,
        'Proud'       => 1.5,
        'Romantic'    => 1.3,
        'Nurturing'   => 1.0,
        'Playful'     => 0.8,
        'Gentle'      => 0.7,
        'Guarded'     => 0.6,
        'Humble'      => 0.5,
        'Stoic'       => 0.5,
        'Bold'        => 0.4,
        'Independent' => 0.3,
        'Defiant'     => 0.3,
    ];

    // =========================================================================
    // INTERESTS SYSTEM (modulates ALL love languages)
    // =========================================================================

    const INTEREST_TYPES = [
        'combat', 'crafting', 'alchemy', 'enchanting', 'scholarly',
        'nature', 'social', 'domestic', 'adventure', 'spiritual', 'wealth',
    ];

    // Location keyword → interest category mapping
    const KEYWORD_TO_INTEREST = [
        'forge'        => 'crafting',
        'smithy'       => 'crafting',
        'smelter'      => 'crafting',
        'tannery'      => 'crafting',
        'grindstone'   => 'crafting',
        'alchemy'      => 'alchemy',
        'potion_store' => 'alchemy',
        'enchanter'    => 'enchanting',
        'cooking'      => 'domestic',
        'tavern'       => 'social',
        'inn'          => 'social',
        'store'        => 'social',
        'city'         => 'social',
        'town'         => 'social',
        'village'      => 'social',
        'settlement'   => 'social',
        'dungeon'      => 'adventure',
        'ruin'         => 'adventure',
        'nordic_ruin'  => 'adventure',
        'dwemer_ruin'  => 'adventure',
        'cave'         => 'adventure',
        'tomb'         => 'adventure',
        'crypt'        => 'adventure',
        'barrow'       => 'adventure',
        'mine'         => 'adventure',
        'camp'         => 'nature',
        'bandit_camp'  => 'adventure',
        'military_camp'=> 'nature',
        'giant_camp'   => 'nature',
        'farm'         => 'nature',
        'lumber_mill'  => 'nature',
        'library'      => 'scholarly',
        'college'      => 'scholarly',
        'temple'       => 'spiritual',
    ];

    // Item name keyword → interest category (for gift classification)
    const ITEM_INTEREST_MAP = [
        // Combat (weapons + armor)
        'sword' => 'combat', 'axe' => 'combat', 'mace' => 'combat', 'bow' => 'combat',
        'arrow' => 'combat', 'dagger' => 'combat', 'greatsword' => 'combat',
        'battleaxe' => 'combat', 'warhammer' => 'combat', 'shield' => 'combat',
        'armor' => 'combat', 'helmet' => 'combat', 'gauntlet' => 'combat',
        'boots' => 'combat', 'cuirass' => 'combat', 'war ' => 'combat',
        // Crafting
        'ore' => 'crafting', 'ingot' => 'crafting', 'leather' => 'crafting',
        'hide' => 'crafting', 'strip' => 'crafting', 'firewood' => 'crafting',
        // Alchemy
        'potion' => 'alchemy', 'elixir' => 'alchemy', 'poison' => 'alchemy',
        'ingredient' => 'alchemy', 'flower' => 'alchemy', 'root' => 'alchemy',
        'wing' => 'alchemy', 'dust' => 'alchemy', 'salt' => 'alchemy',
        'herb' => 'alchemy', 'mushroom' => 'alchemy', 'petal' => 'alchemy',
        'extract' => 'alchemy', 'eye of' => 'alchemy',
        // Enchanting
        'soul gem' => 'enchanting', 'soul_gem' => 'enchanting',
        'staff' => 'enchanting', 'scroll' => 'enchanting',
        // Scholarly
        'book' => 'scholarly', 'tome' => 'scholarly', 'journal' => 'scholarly',
        'note' => 'scholarly', 'letter' => 'scholarly', 'map' => 'scholarly',
        'spell tome' => 'scholarly',
        // Nature
        'pelt' => 'nature', 'antler' => 'nature', 'claw' => 'nature',
        'feather' => 'nature', 'tusk' => 'nature', 'bone' => 'nature',
        'scale' => 'nature',
        // Wealth
        'gem' => 'wealth', 'jewel' => 'wealth', 'ruby' => 'wealth',
        'sapphire' => 'wealth', 'emerald' => 'wealth', 'diamond' => 'wealth',
        'necklace' => 'wealth', 'ring' => 'wealth', 'circlet' => 'wealth',
        'gold' => 'wealth', 'silver' => 'wealth',
        // Domestic
        'food' => 'domestic', 'bread' => 'domestic', 'cheese' => 'domestic',
        'meat' => 'domestic', 'stew' => 'domestic', 'pie' => 'domestic',
        'soup' => 'domestic', 'ale' => 'domestic', 'wine' => 'domestic',
        'mead' => 'domestic', 'sweet roll' => 'domestic',
        // Spiritual
        'amulet of' => 'spiritual', 'blessing' => 'spiritual',
        'divine' => 'spiritual', 'holy' => 'spiritual', 'talos' => 'spiritual',
    ];

    // How strongly interest multiplier affects each love language
    // Formula: effectiveMult = 1.0 + (rawMult - 1.0) * weight
    const LL_INTEREST_WEIGHT = [
        'quality_time'         => 1.0,   // Full weight
        'gifts'                => 0.8,   // Strong — gift matching is very relevant
        'acts_of_service'      => 0.6,   // Moderate — context-dependent
        'words_of_affirmation' => 0.4,   // Mild — conversation topic proxy
        'physical_touch'       => 0.15,  // Minimal — slight combat context boost
    ];

    // Class → default interest preferences
    const CLASS_INTEREST_DEFAULTS = [
        'Barbarian' => [
            'combat' => 1.5, 'adventure' => 1.4, 'nature' => 1.3, 'crafting' => 1.2,
            'domestic' => 0.9, 'scholarly' => 0.8, 'social' => 0.8, 'wealth' => 0.9,
            'alchemy' => 0.9, 'enchanting' => 0.9, 'spiritual' => 0.9,
        ],
        'Warrior' => [
            'combat' => 1.5, 'crafting' => 1.3, 'adventure' => 1.2, 'social' => 1.1,
            'nature' => 1.0, 'domestic' => 1.0, 'wealth' => 1.0,
            'scholarly' => 0.8, 'alchemy' => 0.9, 'enchanting' => 0.9, 'spiritual' => 0.9,
        ],
        'Mage' => [
            'scholarly' => 1.5, 'enchanting' => 1.4, 'alchemy' => 1.3, 'spiritual' => 1.1,
            'adventure' => 1.0, 'social' => 1.0, 'domestic' => 1.0, 'wealth' => 1.0,
            'nature' => 0.9, 'crafting' => 0.8, 'combat' => 0.8,
        ],
        'Thief' => [
            'adventure' => 1.4, 'social' => 1.4, 'wealth' => 1.3, 'crafting' => 1.0,
            'domestic' => 0.9, 'nature' => 1.0, 'combat' => 1.0,
            'scholarly' => 0.8, 'enchanting' => 0.9, 'alchemy' => 1.0, 'spiritual' => 0.8,
        ],
        'Ranger' => [
            'nature' => 1.5, 'combat' => 1.3, 'adventure' => 1.3, 'crafting' => 1.2,
            'domestic' => 1.1, 'alchemy' => 1.1,
            'social' => 0.8, 'scholarly' => 0.8, 'enchanting' => 0.9, 'wealth' => 0.9, 'spiritual' => 0.9,
        ],
        'Healer' => [
            'alchemy' => 1.5, 'spiritual' => 1.4, 'scholarly' => 1.2, 'domestic' => 1.2,
            'social' => 1.1, 'nature' => 1.1, 'enchanting' => 1.1,
            'combat' => 0.8, 'adventure' => 0.9, 'crafting' => 0.9, 'wealth' => 0.9,
        ],
        'Noble' => [
            'social' => 1.4, 'wealth' => 1.4, 'scholarly' => 1.2, 'domestic' => 1.1,
            'enchanting' => 1.0, 'spiritual' => 1.0, 'alchemy' => 1.0,
            'crafting' => 0.9, 'nature' => 0.8, 'combat' => 0.8, 'adventure' => 0.9,
        ],
        'Merchant' => [
            'social' => 1.5, 'wealth' => 1.5, 'domestic' => 1.2, 'crafting' => 1.1,
            'scholarly' => 1.0, 'alchemy' => 1.0, 'enchanting' => 1.0,
            'combat' => 0.7, 'adventure' => 0.7, 'nature' => 0.8, 'spiritual' => 0.9,
        ],
    ];

    // Skill name fragments → interest bonus (+0.2 added to preference for high skills)
    const SKILL_INTEREST_BONUS = [
        'two-handed'   => 'combat',
        'one-handed'   => 'combat',
        'archery'      => 'combat',
        'block'        => 'combat',
        'heavy armor'  => 'combat',
        'smithing'     => 'crafting',
        'alchemy'      => 'alchemy',
        'enchanting'   => 'enchanting',
        'destruction'  => 'scholarly',
        'conjuration'  => 'scholarly',
        'alteration'   => 'scholarly',
        'illusion'     => 'scholarly',
        'restoration'  => 'scholarly',
        'light armor'  => 'adventure',
        'sneak'        => 'adventure',
        'lockpicking'  => 'adventure',
        'pickpocket'   => 'social',
        'speech'       => 'social',
    ];

    // =========================================================================
    // CONFIG
    // =========================================================================

    public static function getConfig()
    {
        if (self::$config !== null) {
            return self::$config;
        }

        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) {
                self::$config = self::defaultConfig();
                return self::$config;
            }

            $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = 'relationship_dynamics_config' LIMIT 1");
            if (!is_array($row) || empty($row['value'])) {
                self::$config = self::defaultConfig();
                return self::$config;
            }

            self::$config = json_decode($row['value'], true) ?: self::defaultConfig();
        } catch (Throwable $e) {
            error_log("[RelDyn] Config load error: " . $e->getMessage());
            self::$config = self::defaultConfig();
        }

        return self::$config;
    }

    public static function defaultConfig()
    {
        return [
            'enabled' => true,
            'base_passion_gain' => 2.0,
            'passion_max' => 100.0,
            'jealousy_max' => 100.0,
            'jealousy_decay_per_hour' => 1.5,
            'conflict_threshold_affinity_drop' => 10,
            'conflict_threshold_jealousy' => 40,
            'conflict_resolution_positive_count' => 3,
            'conflict_repair_passion_burst' => 20.0,
            'conflict_repair_passion_mult' => 1.5,
            'reunion_min_hours' => 8,
            'reunion_min_affection' => 40,
            'stage_established_threshold' => 50,
            'stage_deep_threshold' => 200,
            'log_enabled' => false,
        ];
    }

    public static function clearConfigCache()
    {
        self::$config = null;
    }

    public static function isEnabled()
    {
        $cfg = self::getConfig();
        return !empty($cfg['enabled']);
    }

    // =========================================================================
    // NPC DYNAMICS DATA (read/write from nsfw_npc_data.extended_data)
    // =========================================================================

    public static function getDynamics($npcName)
    {
        if (empty($npcName)) return self::defaultDynamics();

        $cacheKey = strtolower($npcName);
        if (isset(self::$npcCache[$cacheKey])) {
            return self::$npcCache[$cacheKey];
        }

        // Primary: read from core_npc_master.extended_data.relationship_dynamics
        try {
            $db = $GLOBALS['db'] ?? null;
            if ($db) {
                $escaped = $db->escape($npcName);
                $row = $db->fetchOne("SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}') LIMIT 1");
                if (is_array($row) && !empty($row['extended_data'])) {
                    $ext = json_decode($row['extended_data'], true) ?: [];
                    $rd = $ext['relationship_dynamics'] ?? null;
                    if (is_array($rd) && !empty($rd)) {
                        self::$npcCache[$cacheKey] = array_merge(self::defaultDynamics(), $rd);
                        return self::$npcCache[$cacheKey];
                    }
                }
            }
        } catch (\Throwable $e) {
            self::log("getDynamics DB error: " . $e->getMessage());
        }

        // Fallback: try nsfw_npc_data (legacy, pre-storage-pivot)
        if (class_exists('NsfwNpcData')) {
            $rd = NsfwNpcData::getKey($npcName, 'relationship_dynamics');
            if (is_array($rd) && !empty($rd)) {
                self::$npcCache[$cacheKey] = array_merge(self::defaultDynamics(), $rd);
                return self::$npcCache[$cacheKey];
            }
        }

        // No data found: return defaults
        $defaults = self::defaultDynamics();
        self::$npcCache[$cacheKey] = $defaults;
        return $defaults;
    }

    public static function saveDynamics($npcName, $dynamics)
    {
        if (empty($npcName)) return false;

        $cacheKey = strtolower($npcName);
        self::$npcCache[$cacheKey] = $dynamics;

        // Primary: save to core_npc_master.extended_data.relationship_dynamics
        try {
            $db = $GLOBALS['db'] ?? null;
            if ($db) {
                $escaped = $db->escape($npcName);
                $jsonDynamics = json_encode($dynamics);
                $escapedJson = $db->escape($jsonDynamics);
                $db->execQuery("UPDATE core_npc_master SET extended_data = jsonb_set(COALESCE(extended_data, '{}'::jsonb), '{relationship_dynamics}', '{$escapedJson}'::jsonb) WHERE lower(npc_name) = lower('{$escaped}')");
                return true;
            }
        } catch (\Throwable $e) {
            self::log("saveDynamics DB error: " . $e->getMessage());
        }

        // Fallback: try nsfw_npc_data (legacy)
        if (class_exists('NsfwNpcData')) {
            return NsfwNpcData::setKey($npcName, 'relationship_dynamics', $dynamics);
        }

        return false;
    }

    public static function defaultDynamics()
    {
        return [
            'love_language_primary'   => null,
            'love_language_secondary' => null,
            'warmth_curve'            => null,

            'passion'                 => 0.0,
            'passion_updated_at'      => 0,
            'passion_sources'         => ['love_match' => 0, 'reunion' => 0, 'dramatic' => 0, 'repair' => 0],

            'jealousy_anger'          => 0.0,
            'jealousy_updated_at'     => 0,
            'jealousy_trigger_npc'    => null,

            'in_conflict'             => false,
            'conflict_entered_at'     => 0,
            'conflict_positive_count' => 0,

            'interaction_count'       => 0,
            'last_interaction_at'     => 0,

            'last_seen_at'            => 0,
            'reunion_spike_given'     => false,

            'total_positive_interactions' => 0,
            'stage'                   => self::STAGE_EARLY,

            'love_language_hints_given' => 0,

            'inferred_temperament'    => null,
        ];
    }

    // =========================================================================
    // LOVE LANGUAGE AUTO-GENERATION
    // =========================================================================

    /**
     * Ensure NPC has love languages assigned. Auto-generates if missing.
     * Priority: MARAS temperament > Sharmat profile > CHIM race/faction
     */
    public static function ensureLoveLanguage($npcName, &$dynamics)
    {
        if (!empty($dynamics['love_language_primary'])) {
            return; // Already set
        }

        $primary = null;
        $secondary = null;
        $temperament = null;
        $warmthCurve = null;

        // Priority 1: MARAS temperament
        $marasTemp = self::getMarasTemperament($npcName);
        if ($marasTemp) {
            $temperament = $marasTemp;
            $primary = self::temperamentToLoveLanguage($marasTemp);
            $warmthCurve = self::temperamentToWarmthCurve($marasTemp);
        }

        // Priority 2: Sharmat profile inference
        if (!$primary) {
            $speechStyle = self::getSharmatSpeechStyle($npcName);
            if ($speechStyle) {
                $primary = self::speechStyleToLoveLanguage($speechStyle);
                $temperament = self::speechStyleToTemperament($speechStyle);
                $warmthCurve = self::temperamentToWarmthCurve($temperament);
            }
        }

        // Priority 3: CHIM race/faction fallback
        if (!$primary) {
            $race = self::getNpcRace($npcName);
            $primary = self::raceToLoveLanguage($race);
            $warmthCurve = self::CURVE_MODERATE; // default
        }

        // Secondary from social context
        $socialClass = self::getSocialClass($npcName);
        $secondary = self::socialClassToLoveLanguage($socialClass);

        // If secondary == primary, rotate
        if ($secondary === $primary) {
            $secondary = self::rotateLoveLanguage($primary);
        }

        $dynamics['love_language_primary'] = $primary ?: self::LL_TIME;
        $dynamics['love_language_secondary'] = $secondary ?: self::LL_WORDS;
        $dynamics['warmth_curve'] = $warmthCurve ?: self::CURVE_MODERATE;
        $dynamics['inferred_temperament'] = $temperament;

        self::log("Auto-gen LL for {$npcName}: primary={$dynamics['love_language_primary']}, secondary={$dynamics['love_language_secondary']}, curve={$dynamics['warmth_curve']}, temp={$temperament}");
    }

    // ---- Love language mapping helpers ----

    private static function temperamentToLoveLanguage($temperament)
    {
        $map = [
            'Romantic'    => self::LL_WORDS,
            'Jealous'     => self::LL_TIME,
            'Proud'       => self::LL_SERVICE,
            'Humble'      => self::LL_GIFTS,
            'Independent' => self::LL_TIME,
        ];
        return $map[$temperament] ?? self::LL_TIME;
    }

    private static function speechStyleToLoveLanguage($style)
    {
        $style = strtolower(trim($style));
        $map = [
            'passionate' => self::LL_WORDS, 'romantic' => self::LL_WORDS, 'seductive' => self::LL_WORDS,
            'submissive' => self::LL_TOUCH, 'shy' => self::LL_TOUCH, 'gentle' => self::LL_TOUCH,
            'dominant' => self::LL_SERVICE, 'aggressive' => self::LL_SERVICE, 'bratty' => self::LL_SERVICE,
            'playful' => self::LL_TIME, 'teasing' => self::LL_TIME, 'flirty' => self::LL_TIME,
            'reserved' => self::LL_GIFTS, 'cold' => self::LL_GIFTS, 'formal' => self::LL_GIFTS,
        ];
        return $map[$style] ?? null;
    }

    private static function speechStyleToTemperament($style)
    {
        $style = strtolower(trim($style));
        $map = [
            'passionate' => 'Romantic', 'romantic' => 'Romantic', 'seductive' => 'Romantic',
            'shy' => 'Anxious', 'submissive' => 'Gentle', 'gentle' => 'Gentle',
            'dominant' => 'Bold', 'aggressive' => 'Defiant', 'bratty' => 'Defiant',
            'playful' => 'Playful', 'teasing' => 'Playful', 'flirty' => 'Playful',
            'reserved' => 'Guarded', 'cold' => 'Stoic', 'formal' => 'Proud',
            'nurturing' => 'Nurturing', 'motherly' => 'Nurturing', 'caring' => 'Nurturing',
            'measured' => 'Guarded', 'cautious' => 'Guarded', 'stoic' => 'Stoic',
            'bold' => 'Bold', 'confident' => 'Bold', 'commanding' => 'Bold',
            'anxious' => 'Anxious', 'nervous' => 'Anxious', 'clingy' => 'Anxious',
            'jealous' => 'Jealous', 'possessive' => 'Jealous',
            'humble' => 'Humble', 'modest' => 'Humble',
            'independent' => 'Independent', 'aloof' => 'Independent',
            'defiant' => 'Defiant', 'rebellious' => 'Defiant',
        ];
        return $map[$style] ?? null;
    }

    private static function raceToLoveLanguage($race)
    {
        $race = strtolower(trim($race ?? ''));
        $map = [
            'khajiit' => self::LL_TOUCH, 'woodelf' => self::LL_TOUCH, 'bosmer' => self::LL_TOUCH,
            'highelf' => self::LL_GIFTS, 'altmer' => self::LL_GIFTS, 'imperial' => self::LL_GIFTS,
            'nord' => self::LL_SERVICE, 'orc' => self::LL_SERVICE, 'orsimer' => self::LL_SERVICE,
            'breton' => self::LL_WORDS, 'darkelf' => self::LL_WORDS, 'dunmer' => self::LL_WORDS,
            'redguard' => self::LL_SERVICE, 'argonian' => self::LL_TIME,
        ];
        return $map[$race] ?? self::LL_TIME;
    }

    private static function socialClassToLoveLanguage($socialClass)
    {
        $class = strtolower(trim($socialClass ?? ''));
        $map = [
            'nobles' => self::LL_GIFTS, 'rulers' => self::LL_GIFTS,
            'wealthy' => self::LL_TIME, 'middle' => self::LL_TIME,
            'working' => self::LL_SERVICE, 'poverty' => self::LL_SERVICE,
            'religious' => self::LL_WORDS,
            'outcast' => self::LL_TOUCH,
        ];
        return $map[$class] ?? self::LL_WORDS;
    }

    private static function rotateLoveLanguage($ll)
    {
        $rotation = [
            self::LL_WORDS   => self::LL_TIME,
            self::LL_TIME    => self::LL_TOUCH,
            self::LL_TOUCH   => self::LL_WORDS,
            self::LL_SERVICE => self::LL_WORDS,
            self::LL_GIFTS   => self::LL_TIME,
        ];
        return $rotation[$ll] ?? self::LL_WORDS;
    }

    private static function temperamentToWarmthCurve($temperament)
    {
        $map = [
            'Romantic'    => self::CURVE_SLOW_BURN,
            'Anxious'     => self::CURVE_QUICK,
            'Playful'     => self::CURVE_QUICK,
            'Bold'        => self::CURVE_MODERATE,
            'Humble'      => self::CURVE_MODERATE,
            'Nurturing'   => self::CURVE_MODERATE,
            'Gentle'      => self::CURVE_SLOW_BURN,
            'Jealous'     => self::CURVE_SLOW_BURN,
            'Defiant'     => self::CURVE_GUARDED,
            'Stoic'       => self::CURVE_GUARDED,
            'Proud'       => self::CURVE_GUARDED,
            'Guarded'     => self::CURVE_GUARDED,
            'Independent' => self::CURVE_GUARDED,
        ];
        return $map[$temperament] ?? self::CURVE_MODERATE;
    }

    // ---- Data source helpers ----

    private static function getMarasTemperament($npcName)
    {
        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) return null;

            $escaped = $db->escape($npcName);
            $row = $db->fetchOne("SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}') LIMIT 1");
            if (!is_array($row) || empty($row['extended_data'])) return null;

            $ext = json_decode($row['extended_data'], true) ?: [];
            $playerName = trim($GLOBALS['PLAYER_NAME'] ?? 'Player');
            $maras = $ext['relationships'][$playerName]['maras'] ?? null;
            return $maras['temperament'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function getSharmatSpeechStyle($npcName)
    {
        if (!class_exists('NsfwNpcData')) return null;
        return NsfwNpcData::getKey($npcName, 'sex_speech_style');
    }

    private static function getNpcRace($npcName)
    {
        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) return null;

            $escaped = $db->escape($npcName);
            $row = $db->fetchOne("SELECT race FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}') LIMIT 1");
            return $row['race'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function getSocialClass($npcName)
    {
        // Try MARAS social_class first
        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) return null;

            $escaped = $db->escape($npcName);
            $row = $db->fetchOne("SELECT extended_data FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}') LIMIT 1");
            if (!is_array($row) || empty($row['extended_data'])) return null;

            $ext = json_decode($row['extended_data'], true) ?: [];
            $playerName = trim($GLOBALS['PLAYER_NAME'] ?? 'Player');
            $maras = $ext['relationships'][$playerName]['maras'] ?? null;
            return $maras['socialClass'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }

    // =========================================================================
    // PASSION CALCULATIONS
    // =========================================================================

    /**
     * Apply time-based passion decay. Call at prerequest time.
     */
    public static function decayPassion(&$dynamics)
    {
        $now = time();
        $lastUpdate = intval($dynamics['passion_updated_at'] ?? 0);
        if ($lastUpdate <= 0) {
            $dynamics['passion_updated_at'] = $now;
            return;
        }

        $hoursSince = ($now - $lastUpdate) / 3600.0;
        if ($hoursSince <= 0) return;

        // Cap decay hours — 0 means no between-session decay (passion frozen when offline)
        $cfg = self::getConfig();
        $maxDecayHours = floatval($cfg['decay_max_hours'] ?? 0);
        if ($maxDecayHours > 0) {
            $hoursSince = min($hoursSince, $maxDecayHours);
        } elseif ($maxDecayHours == 0) {
            // Between-session decay disabled — only decay within active play sessions
            // Cap at 10 minutes to handle normal in-session gaps
            $hoursSince = min($hoursSince, 0.167);
        }

        $curve = $dynamics['warmth_curve'] ?? self::CURVE_MODERATE;
        $params = self::CURVE_PARAMS[$curve] ?? self::CURVE_PARAMS[self::CURVE_MODERATE];
        $decayRate = $params['passion_decay'];

        $decay = $decayRate * $hoursSince;
        $stage = $dynamics['stage'] ?? self::STAGE_EARLY;
        $floor = self::STAGE_PARAMS[$stage]['floor'] ?? 0;

        $dynamics['passion'] = max($floor, floatval($dynamics['passion']) - $decay);
        $dynamics['passion_updated_at'] = $now;
    }

    /**
     * Apply time-based jealousy decay. Call at prerequest time.
     */
    public static function decayJealousy(&$dynamics)
    {
        $now = time();
        $lastUpdate = intval($dynamics['jealousy_updated_at'] ?? 0);
        if ($lastUpdate <= 0 || floatval($dynamics['jealousy_anger'] ?? 0) <= 0) return;

        $hoursSince = ($now - $lastUpdate) / 3600.0;
        if ($hoursSince <= 0) return;

        $cfg = self::getConfig();
        $decayRate = floatval($cfg['jealousy_decay_per_hour'] ?? 1.5);
        $decay = $decayRate * $hoursSince;

        $dynamics['jealousy_anger'] = max(0.0, floatval($dynamics['jealousy_anger']) - $decay);
        $dynamics['jealousy_updated_at'] = $now;
    }

    /**
     * Calculate passion gain from an interaction.
     * Returns the passion gain amount (before adding to pool).
     */
    public static function calculatePassionGain($dynamics, $interactionLoveLanguage)
    {
        $cfg = self::getConfig();
        $baseGain = floatval($cfg['base_passion_gain'] ?? 2.0);

        // Love language multiplier
        $llMult = 1.0;
        if ($interactionLoveLanguage) {
            if ($interactionLoveLanguage === ($dynamics['love_language_primary'] ?? null)) {
                $llMult = 2.0;
            } elseif ($interactionLoveLanguage === ($dynamics['love_language_secondary'] ?? null)) {
                $llMult = 1.5;
            }
        }

        // Diminishing returns multiplier (exponential decay)
        $sessionMult = self::getSessionMultiplier($dynamics);

        // Stage multiplier
        $stage = $dynamics['stage'] ?? self::STAGE_EARLY;
        $stageMult = self::STAGE_PARAMS[$stage]['gain_mult'] ?? 1.0;

        // Temperament multiplier
        $temperament = $dynamics['inferred_temperament'] ?? null;
        $tempMult = self::TEMPERAMENT_PASSION_MULT[$temperament] ?? 1.0;

        // Interest multiplier (context-weighted by shared experience + NPC interests)
        $interestMult = self::getInterestMultiplier($dynamics, $interactionLoveLanguage);

        // Conflict repair bonus
        $repairMult = 1.0;
        if (!empty($dynamics['in_conflict'])) {
            $cfg2 = self::getConfig();
            $repairMult = floatval($cfg2['conflict_repair_passion_mult'] ?? 1.5);
        }

        $gain = $baseGain * $llMult * $sessionMult * $stageMult * $tempMult * $interestMult * $repairMult;

        return max(0.0, $gain);
    }

    /**
     * Add passion to pool, respecting stage ceiling.
     */
    public static function addPassion(&$dynamics, $amount, $source = 'love_match')
    {
        $stage = $dynamics['stage'] ?? self::STAGE_EARLY;
        $ceiling = self::STAGE_PARAMS[$stage]['ceiling'] ?? 100;
        $cfg = self::getConfig();
        $max = min(floatval($cfg['passion_max'] ?? 100.0), $ceiling);

        $dynamics['passion'] = min($max, floatval($dynamics['passion']) + $amount);
        $dynamics['passion_updated_at'] = time();

        // Track source
        if (isset($dynamics['passion_sources'][$source])) {
            $dynamics['passion_sources'][$source] += $amount;
        }
    }

    // =========================================================================
    // DIMINISHING RETURNS
    // =========================================================================

    /**
     * Get the current session multiplier based on exponential decay of interaction count.
     */
    public static function getSessionMultiplier($dynamics)
    {
        $now = time();
        $lastInteraction = intval($dynamics['last_interaction_at'] ?? 0);
        $rawCount = intval($dynamics['interaction_count'] ?? 0);

        if ($rawCount <= 0 || $lastInteraction <= 0) {
            return 1.0;
        }

        $curve = $dynamics['warmth_curve'] ?? self::CURVE_MODERATE;
        $params = self::CURVE_PARAMS[$curve] ?? self::CURVE_PARAMS[self::CURVE_MODERATE];
        $decayRate = $params['decay_rate'];
        $lambda = $params['lambda'];

        // Stage modifier on decay_rate
        $stage = $dynamics['stage'] ?? self::STAGE_EARLY;
        $drMult = self::STAGE_PARAMS[$stage]['dr_mult'] ?? 1.0;
        $effectiveDecayRate = $decayRate * $drMult;

        // Exponential decay of interaction count over real time
        $hoursSince = ($now - $lastInteraction) / 3600.0;
        $effectiveCount = $rawCount * exp(-$hoursSince * $lambda);

        $multiplier = 1.0 - ($effectiveCount * $effectiveDecayRate);

        return max(0.05, $multiplier);
    }

    /**
     * Increment interaction count after an interaction.
     */
    public static function recordInteraction(&$dynamics)
    {
        $now = time();
        $lastInteraction = intval($dynamics['last_interaction_at'] ?? 0);
        $rawCount = intval($dynamics['interaction_count'] ?? 0);

        // Apply exponential decay to existing count before incrementing
        if ($lastInteraction > 0 && $rawCount > 0) {
            $curve = $dynamics['warmth_curve'] ?? self::CURVE_MODERATE;
            $params = self::CURVE_PARAMS[$curve] ?? self::CURVE_PARAMS[self::CURVE_MODERATE];
            $lambda = $params['lambda'];
            $hoursSince = ($now - $lastInteraction) / 3600.0;
            $rawCount = $rawCount * exp(-$hoursSince * $lambda);
        }

        $dynamics['interaction_count'] = intval(ceil($rawCount)) + 1;
        $dynamics['last_interaction_at'] = $now;
        $dynamics['last_seen_at'] = $now;
        $dynamics['reunion_spike_given'] = false;
    }

    // =========================================================================
    // AFFINITY GAIN MULTIPLIER (RPM → Speed)
    // =========================================================================

    /**
     * Calculate the affinity gain multiplier from current passion.
     * passion 0 → 0.3x, passion 50 → 1.15x, passion 100 → 2.0x
     */
    public static function getAffinityGainMultiplier($dynamics)
    {
        $passion = floatval($dynamics['passion'] ?? 0);
        return 0.3 + ($passion / 100.0) * 1.7;
    }

    // =========================================================================
    // REUNION SPIKE
    // =========================================================================

    /**
     * Check for reunion spike. Call at prerequest time.
     * Returns the passion amount added (0 if no reunion).
     */
    public static function checkReunion(&$dynamics, $npcAffection = 0)
    {
        $cfg = self::getConfig();
        $minHours = floatval($cfg['reunion_min_hours'] ?? 8);
        $minAff = intval($cfg['reunion_min_affection'] ?? 40);

        // Already spiked this visit
        if (!empty($dynamics['reunion_spike_given'])) return 0.0;

        $lastSeen = intval($dynamics['last_seen_at'] ?? 0);
        if ($lastSeen <= 0) {
            // First time — initialize, no spike
            $dynamics['last_seen_at'] = time();
            return 0.0;
        }

        // Check affection threshold
        if ($npcAffection < $minAff) return 0.0;

        $hoursApart = (time() - $lastSeen) / 3600.0;
        if ($hoursApart < $minHours) return 0.0;

        // Calculate spike
        $spike = 0.0;
        if ($hoursApart >= 72) {
            $spike = 25.0;
        } elseif ($hoursApart >= 48) {
            $spike = 18.0;
        } elseif ($hoursApart >= 24) {
            $spike = 12.0;
        } elseif ($hoursApart >= 16) {
            $spike = 8.0;
        } else {
            $spike = 5.0;
        }

        // Temperament modifier
        $temperament = $dynamics['inferred_temperament'] ?? null;
        $tempMult = self::TEMPERAMENT_REUNION_MULT[$temperament] ?? 1.0;
        $spike *= $tempMult;

        $dynamics['reunion_spike_given'] = true;

        self::log("Reunion spike for NPC: +{$spike} passion (hours_apart={$hoursApart}, temp_mult={$tempMult})");

        return $spike;
    }

    // =========================================================================
    // JEALOUSY
    // =========================================================================

    /**
     * Check if an NPC should gain jealousy from player's romantic interaction with another NPC.
     *
     * @param string $jealousNpcName The NPC who might be jealous
     * @param string $flirtTargetName The NPC the player is flirting with
     * @param array $jealousDynamics Dynamics data for the jealous NPC
     * @param string|null $relPreference relationship_preference from nsfw_npc_data
     * @param string|null $marasStatus MARAS status (married/engaged/candidate)
     * @param int $marasAffection MARAS affection 0-100
     * @return float Jealousy amount to add
     */
    public static function calculateJealousyGain(
        $jealousNpcName, $flirtTargetName, $jealousDynamics,
        $relPreference = null, $marasStatus = null, $marasAffection = 0
    ) {
        $cfg = self::getConfig();
        $baseGain = 10.0;

        // Temperament multiplier
        $temperament = $jealousDynamics['inferred_temperament'] ?? null;
        $tempMult = self::TEMPERAMENT_JEALOUSY_MULT[$temperament] ?? 1.0;

        // Relationship status multiplier
        $relMult = 1.0;
        if ($marasStatus === 'married') {
            // Check rank for lead vs lower spouse (simplified: assume lead)
            $relMult = 1.5;
        } elseif ($marasStatus === 'engaged') {
            $relMult = 1.2;
        } elseif ($marasStatus === 'candidate' && $marasAffection >= 60) {
            $relMult = 0.8; // boyfriend/girlfriend level
        } else {
            return 0.0; // Not committed enough to be jealous
        }

        // Relationship preference modifier
        if ($relPreference === 'polyamorous') {
            $relMult *= 0.2;
        } elseif ($relPreference === 'not_interested') {
            return 0.0; // Doesn't care
        }

        $gain = $baseGain * $tempMult * $relMult;

        self::log("Jealousy: {$jealousNpcName} gains {$gain} (target={$flirtTargetName}, temp={$tempMult}, rel={$relMult})");

        return max(0.0, min(floatval($cfg['jealousy_max'] ?? 100.0), $gain));
    }

    /**
     * Add jealousy to an NPC's dynamics.
     */
    public static function addJealousy(&$dynamics, $amount, $triggerNpc = null)
    {
        $cfg = self::getConfig();
        $max = floatval($cfg['jealousy_max'] ?? 100.0);

        $dynamics['jealousy_anger'] = min($max, floatval($dynamics['jealousy_anger']) + $amount);
        $dynamics['jealousy_updated_at'] = time();
        if ($triggerNpc) {
            $dynamics['jealousy_trigger_npc'] = $triggerNpc;
        }

        // Check if this triggers conflict
        $threshold = floatval($cfg['conflict_threshold_jealousy'] ?? 40);
        if ($dynamics['jealousy_anger'] >= $threshold && empty($dynamics['in_conflict'])) {
            self::enterConflict($dynamics);
        }
    }

    // =========================================================================
    // CONFLICT / REPAIR
    // =========================================================================

    public static function enterConflict(&$dynamics)
    {
        $dynamics['in_conflict'] = true;
        $dynamics['conflict_entered_at'] = time();
        $dynamics['conflict_positive_count'] = 0;
        self::log("Entered conflict state");
    }

    /**
     * Record a positive interaction during conflict. Check for resolution.
     * Returns passion burst if conflict resolved, 0 otherwise.
     */
    public static function recordConflictPositive(&$dynamics)
    {
        if (empty($dynamics['in_conflict'])) return 0.0;

        $dynamics['conflict_positive_count'] = intval($dynamics['conflict_positive_count']) + 1;

        $cfg = self::getConfig();
        $neededCount = intval($cfg['conflict_resolution_positive_count'] ?? 3);
        $jealousyThreshold = floatval($cfg['conflict_threshold_jealousy'] ?? 40) * 0.5;

        if ($dynamics['conflict_positive_count'] >= $neededCount
            && floatval($dynamics['jealousy_anger']) < $jealousyThreshold) {

            // Conflict resolved
            $dynamics['in_conflict'] = false;
            $dynamics['conflict_positive_count'] = 0;

            $burst = floatval($cfg['conflict_repair_passion_burst'] ?? 20.0);
            self::log("Conflict resolved! Passion burst: +{$burst}");
            return $burst;
        }

        return 0.0;
    }

    /**
     * Check if affinity drop triggers conflict.
     */
    public static function checkAffinityDropConflict(&$dynamics, $affinityDelta)
    {
        if ($affinityDelta >= 0) return;
        if (!empty($dynamics['in_conflict'])) return;

        $cfg = self::getConfig();
        $threshold = intval($cfg['conflict_threshold_affinity_drop'] ?? 10);

        if (abs($affinityDelta) >= $threshold) {
            self::enterConflict($dynamics);
        }
    }

    // =========================================================================
    // RELATIONSHIP STAGES
    // =========================================================================

    /**
     * Check and advance relationship stage if threshold crossed.
     */
    public static function checkStageAdvancement(&$dynamics)
    {
        $cfg = self::getConfig();
        $total = intval($dynamics['total_positive_interactions'] ?? 0);
        $currentStage = $dynamics['stage'] ?? self::STAGE_EARLY;

        $deepThreshold = intval($cfg['stage_deep_threshold'] ?? 200);
        $estThreshold = intval($cfg['stage_established_threshold'] ?? 50);

        $newStage = $currentStage;
        if ($total >= $deepThreshold) {
            $newStage = self::STAGE_DEEP;
        } elseif ($total >= $estThreshold) {
            $newStage = self::STAGE_ESTABLISHED;
        }

        if ($newStage !== $currentStage) {
            self::log("Stage advanced: {$currentStage} → {$newStage} (interactions: {$total})");
            $dynamics['stage'] = $newStage;
        }
    }

    // =========================================================================
    // INTERACTION CLASSIFICATION
    // =========================================================================

    /**
     * Classify the current interaction into a love language category.
     *
     * @param array $gameRequest The game request array
     * @param string|null $npcMood The NPC's mood after this interaction
     * @return string|null Love language constant or null if unclassifiable
     */
    public static function classifyInteraction($gameRequest, $npcMood = null)
    {
        $type = $gameRequest[0] ?? '';
        $action = $gameRequest[3] ?? '';

        // Physical touch
        if (in_array($type, ['ext_nsfw_physics', 'ext_nsfw_physics_raw'])) {
            return self::LL_TOUCH;
        }
        if (stripos($action, 'ExtCmdHug') !== false || stripos($action, 'ExtCmdKiss') !== false) {
            return self::LL_TOUCH;
        }
        if (stripos($action, 'ExtCmdStartMassage') !== false) {
            return self::LL_TOUCH;
        }
        // OStim scene events
        if (stripos($action, 'OStim') !== false || stripos($type, 'ostim') !== false) {
            return self::LL_TOUCH;
        }

        // Gifts (MARAS gift sync)
        if ($type === 'maras_sync' && stripos($action, 'gift') !== false) {
            return self::LL_GIFTS;
        }

        // Words of affirmation (flirty/loving mood)
        $romanticMoods = ['flirty', 'loving', 'lovely', 'playful', 'seductive', 'aroused', 'charming', 'affectionate'];
        if ($npcMood && in_array(strtolower($npcMood), $romanticMoods)) {
            return self::LL_WORDS;
        }

        // Acts of service (combat/quest/protective context)
        if ($type === 'maras_sync' && stripos($action, 'promotion') !== false) {
            return self::LL_SERVICE;
        }
        // Combat-together events — fighting alongside = acts of service
        $combatTypes = ['combatend', 'combatendmighty', 'bleedout'];
        if (in_array($type, $combatTypes)) {
            return self::LL_SERVICE;
        }
        // Give/trade item actions — providing for someone = acts of service
        if (stripos($action, 'ExtCmdGiveItem') !== false || stripos($action, 'ExtCmdTradeItem') !== false) {
            return self::LL_SERVICE;
        }
        // Post-combat dialogue — talking after fighting together
        if (in_array($type, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s', 'rechat'])) {
            if (self::isNpcInCombatRecently($gameRequest)) {
                return self::LL_SERVICE;
            }
        }
        // Service-oriented moods — NPC feels protected/grateful
        $serviceMoods = ['grateful', 'protective', 'loyal', 'admiring', 'relieved', 'safe'];
        if ($npcMood && in_array(strtolower($npcMood), $serviceMoods)) {
            return self::LL_SERVICE;
        }

        // Quality time (regular conversation)
        $dialogueTypes = ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s', 'rechat'];
        if (in_array($type, $dialogueTypes)) {
            return self::LL_TIME;
        }

        return null;
    }

    /**
     * Check if the NPC was in combat recently (within last 5 minutes real-time).
     * Queries MinAI actor variables if available, falls back to conf_opts.
     */
    private static function isNpcInCombatRecently($gameRequest)
    {
        $npcName = $GLOBALS['HERIKA_NAME'] ?? '';
        if (empty($npcName)) return false;

        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) return false;

            // Check MinAI inCombat flag via conf_opts
            $key = '_minai_' . strtolower($npcName) . '//incombat';
            $row = $db->fetchOne(
                "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($key)
            );
            if ($row && strtolower(trim($row['value'])) === 'true') {
                return true;
            }

            // Check InCombatState (0=none, 1=searching, 2=in combat)
            $key2 = '_minai_' . strtolower($npcName) . '//incombatstate';
            $row2 = $db->fetchOne(
                "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($key2)
            );
            if ($row2 && intval($row2['value']) >= 1) {
                return true;
            }
        } catch (\Throwable $e) {
            // Silently fail — combat detection is a bonus, not critical
        }

        return false;
    }

    // =========================================================================
    // INTEREST-WEIGHTED PASSION — Detection, Classification & Preferences
    // =========================================================================

    // minai_items.category → interest mapping (100% coverage for giveable items)
    const ITEM_CATEGORY_TO_INTEREST = [
        'Weapon'     => 'combat',
        'Armor'      => 'combat',
        'Ammo'       => 'combat',
        'Potion'     => 'alchemy',
        'Ingredient' => 'alchemy',
        'Book'       => 'scholarly',
        'Scroll'     => 'enchanting',
        'SoulGem'    => 'enchanting',
        'Key'        => 'adventure',
        'Currency'   => 'wealth',
        'Light'      => 'domestic',
        'Misc'       => null, // falls through to keyword matching
    ];

    // Oghma knowledge_class → interest mapping (for items with lore entries)
    const KNOWLEDGE_CLASS_TO_INTEREST = [
        'blacksmith' => 'crafting',
        'alchemist'  => 'alchemy',
        'mage'       => 'enchanting',
        'scholar'    => 'scholarly',
        'priest'     => 'spiritual',
        'noble'      => 'wealth',
        'hunter'     => 'nature',
        'thief'      => 'adventure',
    ];

    /**
     * Classify an item name into an interest category.
     * 3-tier lookup: minai_items.category → oghma.knowledge_class → keyword fallback
     */
    public static function classifyItemInterest($itemName)
    {
        if (empty($itemName)) return null;

        $db = $GLOBALS['db'] ?? null;

        // Tier 1: minai_items.category (100% coverage for registered items)
        if ($db) {
            try {
                $row = $db->fetchOne(
                    "SELECT category FROM minai_items WHERE lower(name) = lower("
                    . $db->escapeLiteral(trim($itemName)) . ") LIMIT 1"
                );
                if ($row && !empty($row['category'])) {
                    $interest = self::ITEM_CATEGORY_TO_INTEREST[$row['category']] ?? null;
                    if ($interest) return $interest;
                    // category=Misc falls through to Tier 2
                }
            } catch (\Throwable $e) {}
        }

        // Tier 2: oghma.knowledge_class (8% coverage, finer classification)
        if ($db) {
            try {
                $topic = strtolower(str_replace(' ', '_', trim($itemName)));
                $row = $db->fetchOne(
                    "SELECT knowledge_class, category FROM oghma WHERE lower(topic) = "
                    . $db->escapeLiteral($topic) . " LIMIT 1"
                );
                if ($row && !empty($row['knowledge_class'])) {
                    // knowledge_class can be CSV: "blacksmith, alchemist"
                    $classes = array_map('trim', explode(',', strtolower($row['knowledge_class'])));
                    foreach ($classes as $kc) {
                        if (isset(self::KNOWLEDGE_CLASS_TO_INTEREST[$kc])) {
                            return self::KNOWLEDGE_CLASS_TO_INTEREST[$kc];
                        }
                    }
                }
                // Oghma category fallback
                if ($row && !empty($row['category'])) {
                    $catMap = ['artifacts' => 'adventure', 'equipment' => 'crafting', 'items' => 'domestic', 'spells' => 'enchanting'];
                    if (isset($catMap[$row['category']])) {
                        return $catMap[$row['category']];
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Tier 3: keyword fallback (for items not in any DB)
        $lower = strtolower(trim($itemName));
        $map = self::ITEM_INTEREST_MAP;
        uksort($map, function($a, $b) { return strlen($b) - strlen($a); });
        foreach ($map as $keyword => $interest) {
            if (strpos($lower, strtolower($keyword)) !== false) {
                return $interest;
            }
        }

        return null;
    }

    /**
     * Detect interest context appropriate for the current love language.
     */
    public static function detectInterestContext($interactionLL)
    {
        switch ($interactionLL) {
            case self::LL_TIME:
            case self::LL_WORDS:
                return self::detectCurrentInterest();

            case self::LL_GIFTS:
                return self::detectGiftInterest();

            case self::LL_SERVICE:
                if (self::isNpcInCombatRecently($GLOBALS['gameRequest'] ?? [])) {
                    return 'combat';
                }
                $itemInterest = self::detectGiftInterest();
                if ($itemInterest) return $itemInterest;
                return self::detectCurrentInterest();

            case self::LL_TOUCH:
                if (self::isNpcInCombatRecently($GLOBALS['gameRequest'] ?? [])) {
                    return 'combat';
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Detect interest category from a gift/item interaction.
     * Extracts item name from LLM response or gameRequest action, then classifies.
     */
    private static function detectGiftInterest()
    {
        // Source 1: LLM response item field
        $llmResponse = $GLOBALS['LAST_LLM_RESPONSE'] ?? null;
        if (is_array($llmResponse) && !empty($llmResponse['item'])) {
            $interest = self::classifyItemInterest($llmResponse['item']);
            if ($interest) return $interest;
        }

        // Source 2: gameRequest action (ExtCmdGiveItem@ItemName:Count)
        $action = $GLOBALS['gameRequest'][3] ?? '';
        if (preg_match('/ExtCmd(?:Give|Trade)Item@([^:\r\n]+)/i', $action, $m)) {
            $interest = self::classifyItemInterest(trim($m[1]));
            if ($interest) return $interest;
        }

        return null;
    }

    /**
     * Detect current interest from location keywords and actor state.
     */
    public static function detectCurrentInterest()
    {
        $npcName = $GLOBALS['HERIKA_NAME'] ?? '';
        if (empty($npcName)) return null;

        $db = $GLOBALS['db'] ?? null;
        if (!$db) return null;

        $interest = null;

        try {
            // 1. Check location keywords
            $key = '_minai_' . strtolower($npcName) . '//locationkeywords';
            $row = $db->fetchOne(
                "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($key)
            );
            if ($row && !empty($row['value'])) {
                $keywords = array_map('trim', explode('~', strtolower($row['value'])));
                foreach ($keywords as $kw) {
                    if (isset(self::KEYWORD_TO_INTEREST[$kw])) {
                        $interest = self::KEYWORD_TO_INTEREST[$kw];
                        break;
                    }
                }
            }

            // 2. Sneaking overrides to adventure (unless already in adventure)
            $sneakKey = '_minai_' . strtolower($npcName) . '//issneaking';
            $sneakRow = $db->fetchOne(
                "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($sneakKey)
            );
            if ($sneakRow && strtolower(trim($sneakRow['value'])) === 'true') {
                if ($interest !== 'adventure') {
                    $interest = 'adventure';
                }
            }

            // 3. On mount = adventure
            $mountKey = '_minai_' . strtolower($npcName) . '//isonmount';
            $mountRow = $db->fetchOne(
                "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($mountKey)
            );
            if ($mountRow && strtolower(trim($mountRow['value'])) === 'true') {
                $interest = 'adventure';
            }

            // 4. Sitting outdoors = nature
            if (!$interest) {
                $sitKey = '_minai_' . strtolower($npcName) . '//sitstate';
                $sitRow = $db->fetchOne(
                    "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($sitKey)
                );
                $interiorKey = '_minai_' . strtolower($npcName) . '//isinterior';
                $intRow = $db->fetchOne(
                    "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($interiorKey)
                );
                $isSitting = ($sitRow && intval($sitRow['value']) == 3);
                $isInterior = ($intRow && strtolower(trim($intRow['value'])) === 'true');

                if ($isSitting && !$isInterior) {
                    $interest = 'nature';
                }
            }

            // 5. Exterior + no other match = nature
            if (!$interest) {
                $interiorKey = '_minai_' . strtolower($npcName) . '//isinterior';
                $intRow = $db->fetchOne(
                    "SELECT value FROM conf_opts WHERE lower(id) = " . $db->escapeLiteral($interiorKey)
                );
                if ($intRow && strtolower(trim($intRow['value'])) !== 'true') {
                    $interest = 'nature';
                }
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return $interest;
    }

    /**
     * Get or auto-generate interest preferences for an NPC.
     * Checks for manual 'interests' key, falls back to old 'activity_preferences', then auto-gen.
     */
    public static function getInterests($dynamics)
    {
        if (!empty($dynamics['interests']) && is_array($dynamics['interests'])) {
            return $dynamics['interests'];
        }

        // Backward compat: migrate old activity_preferences
        if (!empty($dynamics['activity_preferences']) && is_array($dynamics['activity_preferences'])) {
            return self::migrateOldPreferences($dynamics['activity_preferences']);
        }

        return self::generateInterests();
    }

    /**
     * Migrate old activity_preferences keys to new interest categories.
     */
    private static function migrateOldPreferences($oldPrefs)
    {
        $migration = [
            'smithing'   => 'crafting',
            'dungeon'    => 'adventure',
            'wilderness' => 'nature',
            'tavern'     => 'social',
            'studying'   => 'scholarly',
            'cooking'    => 'domestic',
            'exploring'  => 'adventure',
            'traveling'  => 'adventure',
            'camping'    => 'nature',
            'alchemy'    => 'alchemy',
            'enchanting' => 'enchanting',
        ];

        $newPrefs = [];
        foreach ($oldPrefs as $oldKey => $value) {
            $newKey = $migration[$oldKey] ?? null;
            if ($newKey) {
                $newPrefs[$newKey] = max($newPrefs[$newKey] ?? 0.5, floatval($value));
            }
        }
        foreach (self::INTEREST_TYPES as $type) {
            if (!isset($newPrefs[$type])) $newPrefs[$type] = 1.0;
        }
        return $newPrefs;
    }

    /**
     * Auto-generate interest preferences from NPC class and skills.
     */
    public static function generateInterests()
    {
        $npcName = $GLOBALS['HERIKA_NAME'] ?? '';
        $prefs = [];

        // Try to get NPC class
        $class = null;
        try {
            $db = $GLOBALS['db'] ?? null;
            if ($db && !empty($npcName)) {
                $row = $db->fetchOne(
                    "SELECT extended_data->>'class' AS class FROM core_npc_master WHERE npc_name = "
                    . $db->escapeLiteral($npcName)
                );
                if ($row && !empty($row['class'])) {
                    $class = $row['class'];
                    foreach (array_keys(self::CLASS_INTEREST_DEFAULTS) as $className) {
                        if (stripos($class, $className) !== false) {
                            $class = $className;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        $prefs = self::CLASS_INTEREST_DEFAULTS[$class] ?? [
            'combat' => 1.0, 'crafting' => 1.0, 'alchemy' => 1.0, 'enchanting' => 1.0,
            'scholarly' => 1.0, 'nature' => 1.0, 'social' => 1.0, 'domestic' => 1.0,
            'adventure' => 1.0, 'spiritual' => 1.0, 'wealth' => 1.0,
        ];

        // Skill-based bonuses
        $skills = $GLOBALS['HERIKA_SKILLS'] ?? '';
        if (!empty($skills)) {
            foreach (self::SKILL_INTEREST_BONUS as $skillFragment => $interestType) {
                if (stripos($skills, $skillFragment) !== false) {
                    if (preg_match('/' . preg_quote($skillFragment, '/') . '\D*(\d+)/i', $skills, $m)) {
                        if (intval($m[1]) >= 20) {
                            $prefs[$interestType] = ($prefs[$interestType] ?? 1.0) + 0.2;
                        }
                    } else {
                        $prefs[$interestType] = ($prefs[$interestType] ?? 1.0) + 0.1;
                    }
                }
            }
        }

        foreach ($prefs as $k => $v) {
            $prefs[$k] = max(0.5, min(2.0, $v));
        }

        return $prefs;
    }

    /**
     * Get interest-weighted passion multiplier for the given love language.
     * Detects context, looks up NPC interest, applies LL-appropriate weight.
     */
    public static function getInterestMultiplier($dynamics, $interactionLL = null)
    {
        if ($interactionLL === null) return 1.0;

        $interest = self::detectInterestContext($interactionLL);
        if ($interest === null) return 1.0;

        $prefs = self::getInterests($dynamics);
        $rawMult = floatval($prefs[$interest] ?? 1.0);

        // Apply LL weight to temper the multiplier
        $weight = self::LL_INTEREST_WEIGHT[$interactionLL] ?? 0.5;
        return 1.0 + ($rawMult - 1.0) * $weight;
    }

    /**
     * Get narrative text describing NPC's reaction to the current interest context.
     * Uses raw multiplier (not LL-weighted) for narrative accuracy.
     */
    public static function getInterestResonanceText($npcName, $interest, $multiplier)
    {
        if ($interest === null || abs($multiplier - 1.0) < 0.05) {
            return null;
        }

        $player = $GLOBALS['PLAYER_NAME'] ?? 'the player';

        if ($multiplier >= 1.3) {
            $positive = [
                'combat'     => "{$npcName} is energized — there's a shared intensity here, a mutual understanding of what it means to fight and survive alongside {$player}.",
                'crafting'   => "{$npcName} is drawn to the rhythm of creation — shaping raw materials into something lasting. Being here with {$player} feels right.",
                'alchemy'    => "{$npcName} watches the ingredients combine with genuine fascination — there's something meditative about this precise, analytical work.",
                'enchanting' => "{$npcName} studies the enchantment process with quiet intensity — the way magic and matter intertwine speaks to something deep.",
                'scholarly'  => "{$npcName} is absorbed in the knowledge around them — a rare, unguarded enthusiasm. Sharing this with {$player} feels intimate.",
                'nature'     => "{$npcName} breathes easier out here — the open sky, the quiet of wild places. More themselves here than behind walls.",
                'social'     => "{$npcName} settles into the social warmth of this place — surrounded by stories and strangers, sharing a moment of normalcy with {$player}.",
                'domestic'   => "{$npcName} finds unexpected comfort in the simple domestic rhythm — a quiet warmth that catches them off guard.",
                'adventure'  => "{$npcName} is alert and engaged — exploring together, relying on each other. This is where trust is forged.",
                'spiritual'  => "{$npcName} feels a quiet reverence in this place — the sacred atmosphere resonates with something deep and personal.",
                'wealth'     => "{$npcName}'s eyes light up — beauty and craftsmanship speak to something they genuinely respect and appreciate.",
            ];
            return $positive[$interest] ?? null;
        } elseif ($multiplier <= 0.85) {
            $negative = [
                'combat'     => "{$npcName} endures the violence but takes no particular satisfaction in it.",
                'crafting'   => "{$npcName} tolerates the heat and noise but finds little to engage with here.",
                'alchemy'    => "{$npcName} watches the work with polite patience, but their attention drifts.",
                'enchanting' => "{$npcName} waits while the enchanting proceeds — it holds little personal interest.",
                'scholarly'  => "{$npcName} tries to engage with the scholarly work but their attention wanders.",
                'nature'     => "{$npcName} endures the open wild but prefers more structured surroundings.",
                'social'     => "{$npcName} scans for the exit. Crowded places and idle chatter drain their patience.",
                'domestic'   => "{$npcName} stands aside — the domestic ritual doesn't quite land for them.",
                'adventure'  => "{$npcName} stays alert but finds no joy in these depths — they'd rather be elsewhere.",
                'spiritual'  => "{$npcName} respects the place but feels no personal connection to the sacred.",
                'wealth'     => "{$npcName} is unmoved by material displays — value means something different to them.",
            ];
            return $negative[$interest] ?? null;
        }

        return null;
    }

    /** @deprecated Use detectCurrentInterest() */
    public static function detectCurrentActivity()
    {
        return self::detectCurrentInterest();
    }

    /** @deprecated Use getInterests() */
    public static function getActivityPreferences($dynamics)
    {
        return self::getInterests($dynamics);
    }

    /** @deprecated Use generateInterests() */
    public static function generateActivityPreferences()
    {
        return self::generateInterests();
    }

    /** @deprecated Use getInterestMultiplier() */
    public static function getActivityMultiplier($dynamics, $activity = null)
    {
        if ($activity === null) {
            $activity = self::detectCurrentInterest();
        }
        if ($activity === null) return 1.0;
        $prefs = self::getInterests($dynamics);
        return floatval($prefs[$activity] ?? 1.0);
    }

    /** @deprecated Use getInterestResonanceText() */
    public static function getActivityResonanceText($npcName, $activity, $multiplier)
    {
        return self::getInterestResonanceText($npcName, $activity, $multiplier);
    }

    // =========================================================================
    // REUNION TEXT (temperament-aware)
    // =========================================================================

    /**
     * Generate temperament-appropriate reunion narrative text.
     *
     * @param string $npcName NPC name
     * @param string $temperament Inferred temperament (Romantic, Independent, etc.)
     * @param float $hoursApart Real-time hours since last seen
     * @param string $player Player name
     * @return string|null Context text or null if no reunion
     */
    public static function getReunionText($npcName, $temperament, $hoursApart, $player)
    {
        if ($hoursApart < 8) return null;

        // Time tier: long (48h+), medium (24h+), short (8h+)
        if ($hoursApart >= 48) {
            $tier = 'long';
        } elseif ($hoursApart >= 24) {
            $tier = 'medium';
        } else {
            $tier = 'short';
        }

        $texts = [
            'Romantic' => [
                'long'   => "{$npcName} hasn't seen {$player} in far too long — there's a rush of emotion, relief and warmth flooding back at seeing them again. The urge to close the distance is overwhelming.",
                'medium' => "{$npcName} missed {$player} — seeing them again brings a wave of warmth and the urge to close the distance between them.",
                'short'  => "{$npcName} is genuinely glad to see {$player} again — a warmth that shows in her eyes before she can hide it.",
            ],
            'Independent' => [
                'long'   => "{$npcName} notes {$player}'s return with a measured look. Something eases in her posture — barely perceptible — though her expression gives nothing away. She noticed the absence more than she expected to.",
                'medium' => "{$npcName} registers {$player}'s presence. A pause — almost imperceptible — before she continues what she was doing. The silence between them is slightly warmer than it was before.",
                'short'  => "{$npcName} acknowledges {$player}'s return with a slight nod. If she is pleased to see them, it shows only in the fact that she looked up at all.",
            ],
            'Proud' => [
                'long'   => "{$npcName} composes herself at seeing {$player} again after so long. Something flickers behind her eyes — quickly mastered. She would never admit how much the absence weighed on her.",
                'medium' => "{$npcName} carries herself with deliberate poise as {$player} returns. She has things to say about the absence, but they will keep. For now, she allows a measured warmth.",
                'short'  => "{$npcName} greets {$player}'s return with composure and the faintest warming of her tone.",
            ],
            'Jealous' => [
                'long'   => "{$npcName} stares at {$player} with an intensity that holds both relief and accusation. Where have they been? Who were they with? The questions burn behind her eyes even as warmth floods back.",
                'medium' => "{$npcName} is clearly relieved to see {$player}, but an edge of anxiety lingers — a need to know where they were and why they stayed away.",
                'short'  => "{$npcName} watches {$player} return with sharp eyes. Glad, yes — but watchful. Already cataloguing whether anything has changed.",
            ],
            'Humble' => [
                'long'   => "{$npcName} quietly brightens at {$player}'s return, like a hearth rekindled after a long cold. She doesn't demand explanations — she's simply, genuinely glad they came back.",
                'medium' => "{$npcName} greets {$player} with a warm, unguarded smile. The relief is honest and unhidden. She doesn't try to make it more or less than it is.",
                'short'  => "{$npcName} looks up at {$player}'s return with quiet pleasure. A small, genuine warmth.",
            ],
            'Anxious' => [
                'long'   => "{$npcName} freezes at the sight of {$player}. Relief crashes into hurt crashes into desperate gladness. The words come too fast — 'Where were you? I thought — never mind. You're here.'",
                'medium' => "{$npcName} visibly exhales seeing {$player}. The tension she's been carrying dissolves into nervous warmth. She moves closer almost involuntarily.",
                'short'  => "{$npcName} brightens immediately at {$player}'s return, then catches herself — tries to play it cool. Fails.",
            ],
            'Bold' => [
                'long'   => "{$npcName} strides toward {$player} without hesitation. 'About time.' The directness masks the depth of what she felt during the absence.",
                'medium' => "{$npcName} greets {$player} with confident warmth. No games, no pretense. She's glad they're here and she shows it plainly.",
                'short'  => "{$npcName} acknowledges {$player}'s return with a firm nod and the ghost of a smile. 'Missed the action.'",
            ],
            'Playful' => [
                'long'   => "{$npcName} greets {$player} with an exaggerated pout. 'Oh, you're alive. I was about to give away your things.' The lightness barely hides how much she missed them.",
                'medium' => "{$npcName} flashes a grin at {$player}. 'Couldn't stay away, could you?' There's genuine warmth under the teasing.",
                'short'  => "{$npcName} gives {$player} a playful look. 'Back already? I was just getting comfortable.'",
            ],
            'Nurturing' => [
                'long'   => "{$npcName} searches {$player}'s face with quiet concern — are they hurt? Tired? Hungry? The questions are gentle but thorough. She's been worrying.",
                'medium' => "{$npcName} greets {$player} with a warm, steady presence. 'You look tired. Come sit.' Caretaking first, everything else after.",
                'short'  => "{$npcName} smiles warmly at {$player}'s return. A quiet check — eyes scanning for injury — before relaxing.",
            ],
            'Gentle' => [
                'long'   => "{$npcName} looks at {$player} for a long moment without speaking. When the words come, they're soft. 'I'm glad.' That's all. It's enough.",
                'medium' => "{$npcName} greets {$player} with a soft warmth that radiates without effort. Her presence says what her words don't.",
                'short'  => "{$npcName} offers {$player} a gentle smile. Understated, sincere.",
            ],
            'Guarded' => [
                'long'   => "{$npcName} studies {$player} from across the room. Something shifts behind her eyes — a wall lowering a fraction. She doesn't approach. But she doesn't look away either.",
                'medium' => "{$npcName} notes {$player}'s return with careful neutrality. Only the slight easing of her shoulders betrays that she noticed the absence.",
                'short'  => "{$npcName} glances at {$player}. A beat longer than necessary. Then back to what she was doing.",
            ],
            'Stoic' => [
                'long'   => "{$npcName} stands still as {$player} approaches. Her expression is unreadable. But she turns to face them fully — and that, from her, is a declaration.",
                'medium' => "{$npcName} acknowledges {$player} with the barest inclination of her head. The silence that follows is not cold. It's loaded.",
                'short'  => "{$npcName} meets {$player}'s eyes briefly. Says nothing. The corner of her mouth moves — not quite a smile.",
            ],
            'Defiant' => [
                'long'   => "{$npcName} looks {$player} up and down. 'You look like hell. Good — means you were doing something.' The defiance is the affection.",
                'medium' => "{$npcName} gives {$player} a sharp grin. 'Didn't think you'd come crawling back this fast.' She's pleased. She'd never say so.",
                'short'  => "{$npcName} smirks at {$player}. 'Back for more?' Challenge as greeting — her native tongue.",
            ],
        ];

        // Default fallback (original text for unknown temperaments)
        $default = [
            'long'   => "{$npcName} hasn't seen {$player} in a long time — there's a rush of emotion, relief and warmth flooding back at seeing them again.",
            'medium' => "{$npcName} missed {$player} — seeing them again brings a wave of warmth and the urge to close the distance between them.",
            'short'  => "{$npcName} is glad to see {$player} again — a pleasant warmth at their return.",
        ];

        $set = $texts[$temperament] ?? $default;
        return $set[$tier] ?? null;
    }

    // =========================================================================
    // EFFECTIVE DISPOSITION
    // =========================================================================

    /**
     * Calculate effective sex_disposal with passion and jealousy overlay.
     */
    public static function getEffectiveDisposition($baseDisposal, $dynamics)
    {
        $passion = floatval($dynamics['passion'] ?? 0);
        $jealousy = floatval($dynamics['jealousy_anger'] ?? 0);

        $effective = $baseDisposal + ($passion * 0.3) - ($jealousy * 0.3);
        return max(0, min(30, intval(round($effective))));
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    public static function log($message)
    {
        $cfg = self::getConfig();
        if (!empty($cfg['log_enabled'])) {
            error_log("[RelDyn] " . $message);
        }
    }

    /**
     * Get a human-readable passion band label.
     */
    public static function getPassionBand($passion)
    {
        if ($passion >= 80) return 'burning';
        if ($passion >= 60) return 'intense';
        if ($passion >= 40) return 'warm';
        if ($passion >= 20) return 'stirring';
        if ($passion > 0)   return 'faint';
        return 'none';
    }

    /**
     * Get a human-readable jealousy band label.
     */
    public static function getJealousyBand($jealousy)
    {
        if ($jealousy >= 80) return 'seething';
        if ($jealousy >= 60) return 'hurt';
        if ($jealousy >= 40) return 'unsettled';
        if ($jealousy >= 20) return 'edgy';
        return 'none';
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    public static function clearNpcCache($npcName = null)
    {
        if ($npcName !== null) {
            unset(self::$npcCache[$npcName]);
        } else {
            self::$npcCache = [];
        }
    }

    // =========================================================================
    // VECTOR OPERATIONS
    // =========================================================================

    public static function cosineSimilarity($vecA, $vecB)
    {
        if (empty($vecA) || empty($vecB)) return 0.0;
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($vecA), count($vecB));
        for ($i = 0; $i < $len; $i++) {
            $dot += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }
        $denom = sqrt($normA) * sqrt($normB);
        return ($denom > 0) ? ($dot / $denom) : 0.0;
    }

    public static function embedInterestVector($interests)
    {
        if (empty($interests)) return null;
        try {
            $parts = [];
            foreach ($interests as $key => $weight) {
                if (is_numeric($weight) && floatval($weight) > 0.5) {
                    $parts[] = "{$key}(" . number_format(floatval($weight), 1) . ")";
                }
            }
            if (empty($parts)) return null;
            $text = implode(' ', $parts);

            $url = 'http://localhost:8082/api/embedtext';
            $payload = json_encode(['text' => $text]);
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 5,
                ],
            ]);
            $result = @file_get_contents($url, false, $ctx);
            if ($result === false) return null;
            $data = json_decode($result, true);
            return $data['vector'] ?? $data['embedding'] ?? $data ?? null;
        } catch (\Throwable $e) {
            self::log("embedInterestVector error: " . $e->getMessage());
            return null;
        }
    }

    public static function getInterestVector(&$dynamics)
    {
        if (!empty($dynamics['_interest_vector'])) {
            return $dynamics['_interest_vector'];
        }
        $interests = self::getInterests($dynamics);
        if (empty($interests)) return null;
        $vec = self::embedInterestVector($interests);
        if ($vec) {
            $dynamics['_interest_vector'] = $vec;
        }
        return $vec;
    }

    // =========================================================================
    // COMBAT SYSTEM
    // =========================================================================

    public static function getCombatContext($npcName)
    {
        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) return null;

            $inCombat = false;
            $healthPct = 1.0;
            $bleedingOut = false;
            $recentKills = 0;
            $source = 'none';

            // Check MinAI combat state from conf_opts
            $npcLower = strtolower($npcName);
            try {
                $combatRow = $db->fetchOne("SELECT value FROM conf_opts WHERE id = 'minai_combat_{$db->escape($npcLower)}'");
                if ($combatRow) {
                    $combatData = json_decode($combatRow['value'], true);
                    $inCombat = !empty($combatData['inCombat']);
                    $healthPct = floatval($combatData['healthPct'] ?? 1.0);
                    $bleedingOut = !empty($combatData['bleedingOut']);
                }
            } catch (\Throwable $e) {}

            // Fallback: check gameRequest for combat event types
            $reqType = $GLOBALS['gameRequest'][0] ?? '';
            $combatTypes = ['radiantcombatfriend', 'combatend', 'combatendmighty', 'death', 'bleedout',
                'minai_bleedoutself', 'minai_combatendvictory', 'minai_combatenddefeat'];
            if (in_array($reqType, $combatTypes)) {
                $inCombat = true;
                $source = 'event';
            }

            // Check MinAI conf_opts flags directly
            try {
                $flagRow = $db->fetchOne("SELECT value FROM conf_opts WHERE id = 'inCombat'");
                if ($flagRow && $flagRow['value'] === '1') {
                    $inCombat = true;
                    $source = 'minai_flag';
                }
            } catch (\Throwable $e) {}

            // Count recent kills from eventlog (last 5 minutes game time)
            try {
                $rows = $db->fetchAll("SELECT COUNT(*) as cnt FROM eventlog WHERE type = 'death' AND people LIKE '%{$db->escape($npcName)}%'");
                $recentKills = intval($rows[0]['cnt'] ?? 0);
            } catch (\Throwable $e) {}

            if (!$inCombat && $recentKills === 0 && !$bleedingOut) return null;

            return [
                'in_combat' => $inCombat,
                'health_pct' => $healthPct,
                'recent_kills' => $recentKills,
                'bleeding_out' => $bleedingOut,
                'source' => $source,
            ];
        } catch (\Throwable $e) {
            self::log("getCombatContext error: " . $e->getMessage());
            return null;
        }
    }

    public static function getRecentCombatSummary($npcName)
    {
        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db) return null;

            $player = $GLOBALS['PLAYER_NAME'] ?? 'the player';
            $combatTypes = "'death','bleedout','combatend','combatendmighty','minai_combatendvictory','minai_combatenddefeat'";

            // Check last 10 combat events, filter to recent ones
            $rows = $db->fetchAll(
                "SELECT type, data, people FROM eventlog WHERE type IN ({$combatTypes}) ORDER BY rowid DESC LIMIT 10"
            );

            if (empty($rows)) return null;

            $npcInvolved = false;
            $killCount = 0;
            $wasBleedout = false;
            $sharedCombat = false;

            foreach ($rows as $row) {
                $people = strtolower($row['people'] ?? '');
                $npcLower = strtolower($npcName);
                $playerLower = strtolower($player);

                if (strpos($people, $npcLower) !== false) {
                    $npcInvolved = true;
                    if ($row['type'] === 'death') $killCount++;
                    if (in_array($row['type'], ['bleedout', 'minai_bleedoutself'])) $wasBleedout = true;
                }
                if (strpos($people, $playerLower) !== false && strpos($people, $npcLower) !== false) {
                    $sharedCombat = true;
                }
            }

            if (!$npcInvolved && !$sharedCombat) return null;

            // Build narrative
            $parts = [];
            if ($sharedCombat) {
                $parts[] = "Survived combat alongside {$player}. Bond forged under pressure.";
            }
            if ($killCount > 0) {
                $parts[] = "Witnessed {$killCount} kill" . ($killCount > 1 ? 's' : '') . " — the violence is fresh.";
            }
            if ($wasBleedout) {
                $parts[] = "Nearly died in the fighting. The vulnerability lingers.";
            }
            if (empty($parts)) {
                $parts[] = "Recent combat still echoes. Adrenaline fading but the tension remains.";
            }

            return implode(' ', $parts);
        } catch (\Throwable $e) {
            self::log("getRecentCombatSummary error: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // ENVIRONMENTAL RESONANCE TEXT
    // =========================================================================

    public static function getEnvironmentalResonanceText($npcName, $interest, $resonance, $location)
    {
        if ($resonance < 0.3) return '';

        $interestTexts = [
            'combat'     => 'the thrill of danger and the weight of weapons',
            'crafting'   => 'the hum of creation, raw materials waiting to become something',
            'alchemy'    => 'the scent of reagents and the promise of discovery',
            'enchanting' => 'the crackle of bound magic and soul energy',
            'scholarly'  => 'ancient knowledge etched into every surface',
            'nature'     => 'the living world breathing around them',
            'social'     => 'the warmth of gathered voices and shared stories',
            'domestic'   => 'the comfort of hearth and home',
            'adventure'  => 'the call of the unknown, paths yet untaken',
            'spiritual'  => 'something sacred resonating in the stones',
            'wealth'     => 'the glint of opportunity and valuable resources',
        ];

        $desc = $interestTexts[$interest] ?? 'something that catches their attention';

        if ($resonance >= 0.6) {
            return "This place resonates deeply with {$npcName} — {$desc}. There is a visible ease, an alertness that speaks to genuine engagement with the surroundings.";
        } else {
            return "Something about {$location} catches {$npcName}'s attention — {$desc}. A quiet interest, not quite fascination, but the environment holds their gaze.";
        }
    }

    // =========================================================================
    // TYPE CONSTRAINT FUNCTIONS
    // =========================================================================

    public static function getBlockedTypes($dynamics, $chimAffinity = null)
    {
        $pref = strtolower(trim($dynamics['relationship_preference'] ?? ''));
        if (empty($pref) || $pref === 'default') return [];

        $blocked = [];
        // Use CHIM affinity if provided (from relationship_system), fall back to dynamics blob
        $aff = ($chimAffinity !== null) ? floatval($chimAffinity) : floatval($dynamics['affinity'] ?? 0);

        switch ($pref) {
            case 'demisexual':
                if ($aff < 60) $blocked[] = 'romantic';
                if ($aff < 80) $blocked[] = 'committed';
                $blocked[] = 'sworn'; // always requires deep bond
                break;
            case 'asexual':
                $blocked[] = 'romantic';
                $blocked[] = 'committed';
                $blocked[] = 'sworn';
                break;
            case 'aromantic':
                $blocked[] = 'romantic';
                $blocked[] = 'committed';
                $blocked[] = 'sworn';
                $blocked[] = 'crush';
                break;
        }

        return $blocked;
    }

    public static function getTypeConstraintPrompt($dynamics, $npcName, $chimAffinity = null)
    {
        $pref = strtolower(trim($dynamics['relationship_preference'] ?? ''));
        if (empty($pref) || $pref === 'default') return '';

        $blocked = self::getBlockedTypes($dynamics, $chimAffinity);
        if (empty($blocked)) return '';

        $prompts = [
            'demisexual' => "{$npcName} forms deep bonds slowly — romantic connection requires genuine trust built over time. Physical intimacy without emotional foundation feels wrong to them.",
            'asexual'    => "{$npcName} does not experience sexual attraction. Deep emotional bonds are possible, but physical intimacy is not something they seek or welcome.",
            'aromantic'  => "{$npcName} does not experience romantic attraction. They can form deep platonic bonds and loyal friendships, but romantic framing feels foreign and uncomfortable.",
        ];

        return $prompts[$pref] ?? '';
    }
}
