<?php

/**
 * Centralized Prompt Caching Configuration
 * 
 * This file defines which prompt sections are ephemeral (change frequently) vs static (rarely change).
 * Used by caching connectors to determine what should be excluded from cache for optimal hit rates.
 * 
 * When adding new sections to buildDynamicBiography() or prompt builders, add them here too!
 */

/**
 * Ephemeral sections that change frequently and should NOT be cached
 * 
 * These are extracted from prompts and re-inserted AFTER the cache control marker
 * to ensure they're always fresh while maximizing cache hit rates.
 */
$GLOBALS['EPHEMERAL_SECTIONS'] = [
    // From buildDynamicBiography() in lib/data_functions.php
    'equipment' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'Current equipped gear - changes when NPC equips/unequips items'
    ],
    'inventory' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'Carried items - changes when picking up/dropping items'
    ],
    'current_condition' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'HP/Magicka/Stamina status - changes frequently in combat and rest'
    ],
    'spells' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'Known spells - changes when learning new spells'
    ],
    'reanimation_status' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'Zombie/undead state - changes if NPC is reanimated'
    ],
    
    // From setActions() in functions/json_response.php
    'available_actions_list' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'Available actions - changes because FUNC_LIST is shuffled on each request'
    ],
    
    // From main.php - Oghma knowledge system
    'knowledge' => [
        'source' => 'system',
        'tag_type' => 'xml',
        'description' => 'Oghma-infused lore knowledge - changes based on conversation topics'
    ],
    
    // From dialogue context in lib/data_functions.php
    'nearby_actors' => [
        'source' => 'dialogue',
        'tag_type' => 'xml',
        'description' => 'NPCs in range - changes as NPCs move in/out of range'
    ],
    'nearby_items' => [
        'source' => 'dialogue',
        'tag_type' => 'xml',
        'description' => 'Items on ground - changes as items spawn/despawn or are picked up'
    ],
    'adventuring_party' => [
        'source' => 'dialogue',
        'tag_type' => 'xml',
        'description' => 'Current party members - changes as party composition changes'
    ],
    'points_of_interest' => [
        'source' => 'dialogue',
        'tag_type' => 'xml',
        'description' => 'Nearby POIs/doors - changes based on location'
    ],
];

/**
 * Static sections that rarely change and SHOULD be cached
 * 
 * These remain in the prompt and get cache control markers applied to them.
 * Documented here for clarity but not actively extracted.
 */
$GLOBALS['STATIC_SECTIONS'] = [
    // From buildDynamicBiography() - character profile fields
    'basic_summary' => 'Character background/history',
    'personality' => 'Character personality traits',
    'appearance' => 'Physical appearance description',
    'relationships' => 'Relationships with other characters',
    'occupation' => 'Job/role in the world',
    'skills' => 'Skill descriptions (not numeric values)',
    'speechstyle' => 'Speaking style and mannerisms',
    'goals' => 'Character goals and motivations',
    
    // From main.php - system instructions
    'prompt_head' => 'Game rules and system instructions',
    'general_instructions' => 'Response format and behavior instructions',
    'knowledge' => 'Oghma infused knowledge (if present)',
    'character' => 'Character definition block',
    
    // Other static content
    'rpg_skills' => 'Skill proficiency levels (changes rarely)',
];

/**
 * Get list of ephemeral section names only
 * @return array Array of section tag names
 */
function getEphemeralSections() {
    return array_keys($GLOBALS['EPHEMERAL_SECTIONS']);
}

/**
 * Get ephemeral sections by source (system vs dialogue)
 * @param string $source 'system' or 'dialogue'
 * @return array Array of section tag names from that source
 */
function getEphemeralSectionsBySource($source) {
    $filtered = [];
    foreach ($GLOBALS['EPHEMERAL_SECTIONS'] as $tag => $config) {
        if ($config['source'] === $source) {
            $filtered[] = $tag;
        }
    }
    return $filtered;
}

/**
 * Check if a section is ephemeral
 * @param string $tagName Section tag name
 * @return bool True if ephemeral, false otherwise
 */
function isEphemeralSection($tagName) {
    return isset($GLOBALS['EPHEMERAL_SECTIONS'][$tagName]);
}

/**
 * Get section configuration
 * @param string $tagName Section tag name
 * @return array|null Configuration array or null if not found
 */
function getSectionConfig($tagName) {
    if (isset($GLOBALS['EPHEMERAL_SECTIONS'][$tagName])) {
        return array_merge(['ephemeral' => true], $GLOBALS['EPHEMERAL_SECTIONS'][$tagName]);
    }
    
    if (isset($GLOBALS['STATIC_SECTIONS'][$tagName])) {
        return ['ephemeral' => false, 'description' => $GLOBALS['STATIC_SECTIONS'][$tagName]];
    }
    
    return null;
}

