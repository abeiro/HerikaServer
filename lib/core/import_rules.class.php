<?php

class ImportRules {
    private $table = "import_rules";
    private $db;

    public function __construct() {
        $this->db = $GLOBALS["db"];
    }

    // Create (Insert)
    public function create($data) {
        $fields = [
            "description",
            "match_name",
            "match_race",
            "match_gender",
            "match_base",
            "match_mods",
            "action",
            "profile",
            "priority",
            "enabled"
        ];

        $filtered = array_intersect_key($data, array_flip($fields));

        // Separate handling for match_mods because parameterized array assignment needs explicit literal
        $modsLiteral = null;
        if (array_key_exists('match_mods', $filtered)) {
            if (is_array($filtered['match_mods'])) {
                $modsLiteral = $this->toPgArray($filtered['match_mods']);
            } elseif (is_string($filtered['match_mods'])) {
                $vals = array_filter(array_map('trim', explode(',', $filtered['match_mods'])), function($v){ return $v !== ''; });
                $modsLiteral = empty($vals) ? null : $this->toPgArray($vals);
            }
            unset($filtered['match_mods']);
        }

        // Handle JSON for action
        if (isset($filtered['action']) && is_array($filtered['action'])) {
            $filtered['action'] = json_encode($filtered['action'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }

        if (isset($filtered["enabled"]) && $filtered["enabled"]) {
            $filtered["enabled"]="TRUE";
        } else
            $filtered["enabled"]="FALSE";


        // Insert base fields
        $this->db->insert($this->table, $filtered);

        // Set match_mods if provided
        if ($modsLiteral !== null) {
            $row = $this->db->fetchOne("SELECT id FROM {$this->table} ORDER BY id DESC LIMIT 1");
            if ($row && isset($row['id'])) {
                $id = (int)$row['id'];
                $modsSql = ($modsLiteral === null) ? 'NULL' : ("'" . $this->db->escape($modsLiteral) . "'::text[]");
                $sql = "UPDATE {$this->table} SET match_mods = {$modsSql} WHERE id = {$id}";
                $this->db->execQuery($sql);
            }
        }

        return true;
    }

    // Read by ID
    public function getById($id) {
        $id = (int)$id;
        $query = "SELECT * FROM {$this->table} WHERE id = $id LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read all (optional WHERE clause)
    public function getAll($where = "TRUE", $orderBy = "priority DESC, id DESC") {
        $query = "SELECT * FROM {$this->table} WHERE $where ORDER BY $orderBy";
        return $this->db->fetchAll($query);
    }

    // Update by ID
    public function update($id, $data) {
        $id = (int)$id;
        $fields = [
            "description",
            "match_name",
            "match_race",
            "match_gender",
            "match_base",
            "match_mods",
            "action",
            "profile",
            "priority",
            "enabled"
        ];

        $filtered = array_intersect_key($data, array_flip($fields));

        // Prepare match_mods literal separately and remove from parameterized update
        $modsLiteral = null;
        $modsProvided = array_key_exists('match_mods', $filtered);
        if ($modsProvided) {
            if (is_array($filtered['match_mods'])) {
                $modsLiteral = $this->toPgArray($filtered['match_mods']);
            } elseif ($filtered['match_mods'] === '' || $filtered['match_mods'] === null) {
                $modsLiteral = null;
            } elseif (is_string($filtered['match_mods'])) {
                $s = trim($filtered['match_mods']);
                if ($s !== '' && $s[0] === '{' && substr($s, -1) === '}') {
                    $modsLiteral = $s;
                } else {
                    $vals = array_filter(array_map('trim', explode(',', $s)), function($v){ return $v !== ''; });
                    $modsLiteral = empty($vals) ? null : $this->toPgArray($vals);
                }
            }
            unset($filtered['match_mods']);
        }

        // Handle JSON for action
        if (isset($filtered['action'])) {
            if (is_array($filtered['action'])) {
                $filtered['action'] = json_encode($filtered['action'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            } elseif ($filtered['action'] === '' || $filtered['action'] === null) {
                $filtered['action'] = null;
            }
        }

        if (isset($filtered["enabled"]) && $filtered["enabled"]) {
            $filtered["enabled"]="TRUE";
        } else
            $filtered["enabled"]="FALSE";

        $where = "id = $id";
        $this->db->updateRow($this->table, $filtered, $where);

        if ($modsProvided) {
            $modsSql = ($modsLiteral === null) ? 'NULL' : ("'" . $this->db->escape($modsLiteral) . "'::text[]");
            $sql = "UPDATE {$this->table} SET match_mods = {$modsSql} WHERE id = {$id}";
            $this->db->execQuery($sql);
        }

        return true;
    }

    // Delete by ID
    public function delete($id) {
        $id = (int)$id;
        $where = "id = $id";
        return $this->db->delete($this->table, $where);
    }

    // Truncate the table
    public function truncate($restart = false, $cascade = false) {
        return $this->db->truncate($this->table, $restart, $cascade);
    }

    // Helper to escape strings
    private function escape($str) {
        return $this->db->escape($str);
    }

    private function toPgArray(array $values) {
        $clean = [];
        foreach ($values as $v) {
            if ($v === null) continue;
            $s = (string)$v;
            if ($s === '') continue;
            // Escape quotes and wrap each element in quotes
            $s = str_replace(['"', '""'], '"', $s);
            $s = str_replace('"', '"', $s);
            $s = str_replace('"', '"', $s);
            $s = str_replace('"', '"', $s);
            $s = str_replace('"', '"', $s);
            $s = str_replace('"', '"', $s);
            // Use simple escaping for double quotes and backslashes
            $s = str_replace(['\\', '"'], ['\\\\', '\\"'], $s);
            $clean[] = '"' . $s . '"';
        }
        if (empty($clean)) return null;
        return '{' . implode(',', $clean) . '}';
    }
}

