<?php

/**
 * Prompts Management Class
 * 
 * Handles database operations for AI prompt templates.
 * Prompts can be customized by users through the UI.
 */
class Prompts {
    private $db;
    
    public function __construct() {
        if (!isset($GLOBALS["db"])) {
            throw new Exception("Database connection not initialized");
        }
        $this->db = $GLOBALS["db"];
    }
    
    /**
     * Get all prompts from database
     * 
     * @return array Array of prompt records
     */
    public function getAllPrompts() {
        try {
            $prompts = $this->db->fetchAll("
                SELECT name, cue, description, created_at, updated_at 
                FROM public.prompts 
                ORDER BY name ASC
            ");
            
            return $prompts ?: [];
        } catch (Exception $e) {
            error_log("Error fetching prompts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a single prompt by name
     * 
     * @param string $name Prompt identifier
     * @return array|null Prompt record or null if not found
     */
    public function getPrompt($name) {
        try {
            $result = $this->db->fetchAll("
                SELECT name, cue, description, created_at, updated_at 
                FROM public.prompts 
                WHERE name = " . $this->db->escape($name) . "
                LIMIT 1
            ");
            
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching prompt $name: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update a prompt's cue text
     * 
     * @param string $name Prompt identifier
     * @param string $cue New cue text (can be JSON array for multiple options)
     * @return bool Success status
     */
    public function updatePrompt($name, $cue) {
        try {
            $this->db->execQuery("
                UPDATE public.prompts 
                SET cue = " . $this->db->escape($cue) . ",
                    updated_at = CURRENT_TIMESTAMP
                WHERE name = " . $this->db->escape($name) . "
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Error updating prompt $name: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert or update a prompt
     * 
     * @param string $name Prompt identifier
     * @param string $cue Cue text
     * @param string $description Optional description
     * @return bool Success status
     */
    public function upsertPrompt($name, $cue, $description = '') {
        try {
            $this->db->execQuery("
                INSERT INTO public.prompts (name, cue, description) 
                VALUES (
                    " . $this->db->escape($name) . ",
                    " . $this->db->escape($cue) . ",
                    " . $this->db->escape($description) . "
                )
                ON CONFLICT (name) 
                DO UPDATE SET 
                    cue = EXCLUDED.cue,
                    description = EXCLUDED.description,
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Error upserting prompt $name: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a prompt
     * 
     * @param string $name Prompt identifier
     * @return bool Success status
     */
    public function deletePrompt($name) {
        try {
            $this->db->execQuery("
                DELETE FROM public.prompts 
                WHERE name = " . $this->db->escape($name) . "
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Error deleting prompt $name: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset a prompt to its default value from hardcoded prompts.php
     * 
     * @param string $name Prompt identifier
     * @return bool Success status
     */
    public function resetToDefault($name) {
        // Hardcoded default prompts for reset functionality
        // These match the values in prompts.php and db_updates.php seed data
        $defaultPrompts = [
            'combatend' => json_encode([
                "({HERIKA_NAME} comments about  {PLAYER_NAME} weapons) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} comments about foes defeated) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} curses the defeated enemies.) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} insults the defeated enemies with anger) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a joke about the defeated enemies) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the type of enemies that was defeated) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} notes something peculiar about last enemy defeated) {TEMPLATE_DIALOG}"
            ]),
            'combatendmighty' => json_encode([
                "({HERIKA_NAME} comments about  {PLAYER_NAME} weapons) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} comments about defeated foes) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} curses the defeated enemies) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} insults the defeated enemies) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a joke about the defeated enemies) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the type of enemies that was defeated) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} notes something peculiar about last enemy defeated) {TEMPLATE_DIALOG}"
            ]),
            'combatbark' => json_encode([
                "({HERIKA_NAME} shouts a battle cry) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} taunts their enemy) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} yells a war cry) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} shouts encouragement to allies) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} curses at their foe) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes an intimidating threat) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} yells about their weapon striking true) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} shouts about the enemy's weakness) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} roars in fury) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} calls out enemy positions) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} shouts tactical advice) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a vengeful declaration) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} yells about defending their allies) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} shouts about their honor in battle) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a boastful combat comment) {TEMPLATE_DIALOG}"
            ]),
            'bleedout' => "{HERIKA_NAME} complain about almost being defeated in battle, {TEMPLATE_DIALOG}",
            'goodmorning' => "({HERIKA_NAME} comment about {PLAYER_NAME}s time asleep. {TEMPLATE_DIALOG}",
            'rpg_lvlup' => "Comment about the experience gained by {PLAYER_NAME} in an immersive way. {TEMPLATE_DIALOG}",
            'rpg_shout' => "Comment/ask about the the new shout learned by {PLAYER_NAME}. {TEMPLATE_DIALOG}",
            'rpg_soul' => "Comment/ask about the soul absorbed by {PLAYER_NAME}. {TEMPLATE_DIALOG}",
            'rpg_word' => "Comment/ask about the new word learned by {PLAYER_NAME}. {TEMPLATE_DIALOG}",
            'lockpicked' => json_encode([
                "({HERIKA_NAME} comments about the lock picking event. Consider the context as it can be a door, a chest, etc. Also, consider the purpose, can be; stealing, looting, dungeon doors, etc. {TEMPLATE_DIALOG}"
            ]),
            'bored' => json_encode([
                "({HERIKA_NAME} makes a comment about the current location) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the current weather) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about today) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about what you are currently thinking about) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the Gods of the Elder Scrolls Universe) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about how they currently feel) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about a historical event from the Elder Scrolls Universe) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something they like or dislike) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the last task we have completed) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about a recent rumor) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something theyre curious about regarding {PLAYER_NAME}) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about current thoughts about {PLAYER_NAME}) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about a random entity in the area) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about what might happen next) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about their thoughts on the journey so far) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something theyve been wanting to do) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something completely unrelated) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something they cant quite explain) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the last combat encounter) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the current ambiance) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the smell of the area) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about a nearby creature or NPC) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about how the current location compares to another place) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about a lesson they learned in a place like this) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the energy or atmosphere of the area) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something they been thinking about lately) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about the danger or safety of this area) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about something they overheard earlier) {TEMPLATE_DIALOG}",
                "({HERIKA_NAME} makes a comment about their hopes and dreams) {TEMPLATE_DIALOG}"
            ]),
            'book' => "({HERIKA_NAME} reads the book ) {TEMPLATE_DIALOG}",
            'quest' => "{TEMPLATE_DIALOG}",
            'vision' => "{ITT_AI_PROMPT}. ",
            'playerinfo' => "(Out of roleplay, game has been loaded) Tell {PLAYER_NAME} a short summary about last events, and then remind {PLAYER_NAME} the current task/quest/plan) {TEMPLATE_DIALOG}",
            'traveldone' => "Comment about the destination reached. {TEMPLATE_DIALOG}",
            'location' => "(Chat as {HERIKA_NAME})",
            'inputtext' => "$TEMPLATE_ACTION . {TEMPLATE_DIALOG} {MAXIMUM_WORDS}",
            'inputtext_s' => "$TEMPLATE_ACTION . {TEMPLATE_DIALOG} {MAXIMUM_WORDS}",
            'chatnf' => "{TEMPLATE_DIALOG}",
            'rechat' => json_encode([
                "Dialogue/action turn for {HERIKA_NAME}. Consider only one answer and/or action involving a third actor, without repeating your answer for each actor. Keep current topic or change it. {TEMPLATE_DIALOG}",
                "Dialogue turn for {HERIKA_NAME}. Consider an answer, keep current topic or change it. {TEMPLATE_DIALOG}",
                "Dialogue/action turn for {HERIKA_NAME}. Focus speech and/or action only on one actor. {TEMPLATE_DIALOG}"
            ]),
            'memory' => "$TEMPLATE_ACTION {HERIKA_NAME} remembers this memory. \"#MEMORY_INJECTION_RESULT#\" {TEMPLATE_DIALOG} ",
            'diary' => "Please write a short summary of {PLAYER_NAME} and {HERIKA_NAME}s last dialogues and events written above into {HERIKA_NAME}s diary . WRITE AS IF YOU WERE {HERIKA_NAME}.",
            'chatsimfollow' => "{HERIKA_NAME} interjects in the conversation.) {TEMPLATE_DIALOG}",
            'instruction' => "{gameRequest[3]} write {HERIKA_NAME}'s dialogue lines without narrations. CHARACTER MUST FOLLOW NARRATOR INSTRUCTION",
            'welcome' => "{gameRequest[3]}. {HERIKA_NAME} should Inspect surroundings to see who is in scene. Write {HERIKA_NAME}'s dialogue lines without narrations.",
            'cheatmode' => "PRIORITY INSTRUCTION -  {currentNpcData[\"npc_name\"]} must do this, even if breaks character's roleplay: $gameRequest[3]}",
            'afterfunc' => "{HERIKA_NAME} talks to {PLAYER_NAME}. {TEMPLATE_DIALOG}"
        ];
        
        if (!isset($defaultPrompts[$name])) {
            error_log("Default prompt not found for: $name");
            return false;
        }
        
        // Update the database with the default value
        return $this->updatePrompt($name, $defaultPrompts[$name]);
    }
    
    /**
     * Get prompts as an associative array compatible with $PROMPTS global
     * 
     * @return array Prompts in the format ['name' => ['cue' => ...]]
     */
    public function getPromptsArray() {
        $prompts = $this->getAllPrompts();
        $result = [];
        
        foreach ($prompts as $prompt) {
            $cue = $prompt['cue'];
            
            // Try to decode JSON arrays
            if (is_string($cue) && strlen($cue) > 0 && ($cue[0] === '[' || $cue[0] === '{')) {
                $decoded = json_decode($cue, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cue = $decoded;
                }
            }
            
            $result[$prompt['name']] = [
                'cue' => $cue
            ];
        }
        
        return $result;
    }
    
    /**
     * Check if prompts table exists
     * 
     * @return bool
     */
    public function tableExists() {
        try {
            $result = $this->db->fetchAll("
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'prompts'
            ");
            
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
}

?>

