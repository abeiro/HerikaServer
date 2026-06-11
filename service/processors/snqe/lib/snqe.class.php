<?php


class SNQEQuestManager
{
    /**
     * Create a new quest with default state and data
     *
     * @param string $quest_id Unique identifier for the quest
     * @param string $code     The PHP code of the quest
     * @param array  $data     Arbitrary quest state data (optional)
     * @param string $state    Quest run state: running|not_running|finished (optional)
     * @param string $title    Quest title (optional)
     * @param string $stage    Quest stage (optional)
     */
    public static function createNewQuest(string $quest_id, string $code, array $data = [], string $state = "not_running", string $title = "", string $stage = "")
    {
        if (self::questExists($quest_id)) {
            throw new \Exception("Quest with id '$quest_id' already exists.");
        }

        if (empty($title)) {
            $title = $quest_id;
        }

        self::upsertQuest($quest_id, $code, $data, $state, $title, $stage);

        $GLOBALS["db"]->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => "snqe_pending_step",
                'value' => ""
            ),
            "id"
        );
    }

    const TABLE_NAME = "sneq_quests";

    /**
     * Create a new quest entry or update if it exists
     *
     * @param string $quest_id Unique identifier for the quest
     * @param string $code     The PHP code of the quest
     * @param array  $data     Arbitrary quest state data
     * @param string $state    Quest run state: running|not_running|finished
     * @param string $title    Quest title (optional)
     * @param string $stage    Quest stage (optional)
     */
    public static function upsertQuest(string $quest_id, string $code, array $data = [], string $state = "not_running", string $title = "", string $stage = "")
    {
        $serializedData = json_encode($data);
        $row = [
            "quest_id" => $quest_id,
            "code" => $code,
            "quest_run_state" => $state,
            "quest_data" => $serializedData,
            "title" => $title,
            "stage" => $stage
        ];
        $GLOBALS["db"]->upsertRow(self::TABLE_NAME, $row, "quest_id='$quest_id'");
    }

    /**
     * Get quest entry by quest_id
     *
     * @param string $quest_id
     * @return array|null Returns associative array of quest data or null if not found
     */
    public static function getQuest(string $quest_id)
    {
        $res = $GLOBALS["db"]->fetchOne(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE quest_id = '" . $GLOBALS["db"]->escape($quest_id) . "'"
        );
        if ($res) {
            $res["quest_data"] = json_decode($res["quest_data"], true);
            if (!isset($res["quest_data"]["started"])) {
                $res["quest_data"]["started"] = $GLOBALS["last_gamets"];
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
    public static function updateQuestState(string $quest_id, string $state)
    {
        $GLOBALS["db"]->updateRow(self::TABLE_NAME, ["quest_run_state" => $state], "quest_id='$quest_id'");
    }


    /**
     * Update quest data (partial or full)
     *
     * @param string $quest_id
     * @param array  $data
     */
    public static function updateQuestData(string $quest_id, array $data)
    {
        $existing = self::getQuest($quest_id);
        if (!$existing)
            return;

        $merged = array_merge($existing["quest_data"] ?? [], $data);
        $merged["lastgamets"] = $GLOBALS["last_gamets"];
        if (isset($data["last_llm_call"]))
            $merged["last_llm_call_topic"] = $GLOBALS["last_gamets"];

        $GLOBALS["db"]->updateRow(self::TABLE_NAME, ["quest_data" => json_encode($merged)], "quest_id='$quest_id'");
    }

    /**
     * Delete a quest entry
     *
     * @param string $quest_id
     */
    public static function deleteQuest(string $quest_id)
    {
        $GLOBALS["db"]->delete(self::TABLE_NAME, "quest_id = '" . $GLOBALS["db"]->escape($quest_id) . "'");
    }

    /**
     * Check if a quest exists
     *
     * @param string $quest_id
     * @return bool
     */
    public static function questExists(string $quest_id): bool
    {
        $res = self::getQuest($quest_id);
        return $res !== null;
    }

    public static function save_quests(int $gamets)
    {
        if (file_exists($GLOBALS["ENGINE_PATH"] . "/log/snqe_state.json")) {
            $state = $GLOBALS["db"]->escape(file_get_contents($GLOBALS["ENGINE_PATH"] . "/log/snqe_state.json"));
            $GLOBALS["db"]->query(
                "INSERT INTO sneq_quests_saved (quest_id, code, created_at,updated_at,quest_run_state, quest_data, title, stage, gamets,\"state\")
                 SELECT t.quest_id, t.code, t.created_at, t.updated_at, t.quest_run_state, t.quest_data, t.title, t.stage, $gamets,'$state'
                 FROM (
                     SELECT DISTINCT ON (quest_id) quest_id, code, created_at, updated_at, quest_run_state, quest_data, title, stage
                     FROM sneq_quests
                     ORDER BY quest_id, updated_at DESC
                 ) t
                 "
            );
        }
    }

    public static function load_quests(int $gamets)
    {

        $GLOBALS["db"]->query("truncate table sneq_quests");
        $runningQuest = $GLOBALS["db"]->fetchOne("SELECT * FROM sneq_quests_saved where gamets<=$gamets order by gamets desc,updated_at desc limit 1");
        if ($runningQuest) {
            $state = json_decode($runningQuest["state"], true);
            file_put_contents($GLOBALS["ENGINE_PATH"] . "/log/snqe_state.json", json_encode($state, JSON_PRETTY_PRINT));
            chmod(filename: $GLOBALS["ENGINE_PATH"] . "/log/snqe_state.json", permissions: 0777);
            $GLOBALS["db"]->insert(
                'sneq_quests',
                [
                    'quest_id' => $runningQuest["quest_id"],
                    'code' => $runningQuest["code"],
                    'quest_run_state' => $runningQuest["quest_run_state"],
                    'quest_data' => $runningQuest["quest_data"],
                    'title' => $runningQuest["title"],
                    'stage' => $runningQuest["stage"],
                    'created_at' => $runningQuest["created_at"],
                    'updated_at' => $runningQuest["updated_at"],
                ]
            );
        } else {
            $stateFile = $GLOBALS["ENGINE_PATH"] . "/log/snqe_state.json";
            if (file_exists($stateFile)) {
                unlink($stateFile);
            }
        }

    }
}
?>
