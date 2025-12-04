<?php 


class SNQEQuestManager {
    /**
     * Create a new quest with default state and data
     *
     * @param string $quest_id Unique identifier for the quest
     * @param string $code     The PHP code of the quest
     * @param array  $data     Arbitrary quest state data (optional)
     * @param string $state    Quest run state: running|not_running|finished (optional)
     */
    public static function createNewQuest(string $quest_id, string $code, array $data = [], string $state = "not_running") {
        if (self::questExists($quest_id)) {
            throw new \Exception("Quest with id '$quest_id' already exists.");
        }
        self::upsertQuest($quest_id, $code, $data, $state);
    }

    const TABLE_NAME = "sneq_quests";

    /**
     * Create a new quest entry or update if it exists
     *
     * @param string $quest_id Unique identifier for the quest
     * @param string $code     The PHP code of the quest
     * @param array  $data     Arbitrary quest state data
     * @param string $state    Quest run state: running|not_running|finished
     */
    public static function upsertQuest(string $quest_id, string $code, array $data = [], string $state = "not_running") {
        $serializedData = json_encode($data);
        $row = [
            "quest_id" => $quest_id,
            "code" => $code,
            "quest_run_state" => $state,
            "quest_data" => $serializedData
        ];
        $GLOBALS["db"]->upsertRow(self::TABLE_NAME, $row, "quest_id='$quest_id'");
    }

    /**
     * Get quest entry by quest_id
     *
     * @param string $quest_id
     * @return array|null Returns associative array of quest data or null if not found
     */
    public static function getQuest(string $quest_id) {
        $res = $GLOBALS["db"]->fetchOne(
            "SELECT * FROM ".self::TABLE_NAME." WHERE quest_id = '".$GLOBALS["db"]->escape($quest_id)."'"
        );
        if ($res) {
            $res["quest_data"] = json_decode($res["quest_data"], true);
            if (sizeof($res["quest_data"])==0) {
                $res["quest_data"]["started"]=$GLOBALS["last_gamets"];
            }
            return $res;
        }
        return null;
    }

    /**
     * Update quest run state
     *
     * @param string $quest_id
     * @param string $state
     */
    public static function updateQuestState(string $quest_id, string $state) {
        $GLOBALS["db"]->updateRow(self::TABLE_NAME, ["quest_run_state" => $state], "quest_id='$quest_id'");
    }

    
    /**
     * Update quest data (partial or full)
     *
     * @param string $quest_id
     * @param array  $data
     */
    public static function updateQuestData(string $quest_id, array $data) {
        $existing = self::getQuest($quest_id);
        if (!$existing) return;

        $merged = array_merge($existing["quest_data"] ?? [], $data);
        $merged["lastgamets"]=$GLOBALS["last_gamets"];
        if (isset($data["last_llm_call"]))
             $merged["last_llm_call_topic"]=$GLOBALS["last_gamets"];
            
        $GLOBALS["db"]->updateRow(self::TABLE_NAME, ["quest_data" => json_encode($merged)], "quest_id='$quest_id'");
    }

    /**
     * Delete a quest entry
     *
     * @param string $quest_id
     */
    public static function deleteQuest(string $quest_id) {
        $GLOBALS["db"]->delete(self::TABLE_NAME, "quest_id = '".$GLOBALS["db"]->escape($quest_id)."'");
    }

    /**
     * Check if a quest exists
     *
     * @param string $quest_id
     * @return bool
     */
    public static function questExists(string $quest_id): bool {
        $res = self::getQuest($quest_id);
        return $res !== null;
    }
}
?>