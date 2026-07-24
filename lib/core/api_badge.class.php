<?php 


 
class ApiBadge {
    private $table = "core_api_badge";
    private $db;

    public function __construct() {
        $this->db = $GLOBALS["db"];
    }

    // Create (Insert)
    public function create($data) {
		$fields = [
			"id",
			"label",
			"api_key"
		];

		$filtered = array_intersect_key($data, array_flip($fields));
		if (isset($filtered['id'])) {
			$filtered['id'] = (int)$filtered['id'];
		}
        
        return $this->db->insertReturningId($this->table, $filtered);
    }

    // Read by ID
    public function getById($id) {
        $id = (int)$id;
        $query = "SELECT * FROM {$this->table} WHERE id = $id LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read by Label (case-insensitive)
    public function getByLabel($label) {
        $escaped = $this->escape($label);
        $query = "SELECT * FROM {$this->table} WHERE LOWER(label) = LOWER('{$escaped}') ORDER BY id ASC LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read all (optional WHERE clause)
    public function getAll($where = "TRUE") {
        $query = "SELECT * FROM {$this->table} WHERE $where";
        return $this->db->fetchAll($query);
    }

    // Update by ID
    public function update($id, $data) {
        $id = (int)$id;
        $fields = [
            "label",
            "api_key"
        ];

        $filtered = array_intersect_key($data, array_flip($fields));
        $where = "id = $id";
        return $this->db->updateRow($this->table, $filtered, $where);
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

    public function getLastError() {
        return $this->db->GetLastError();
    }

    // Upsert using ON CONFLICT (requires UNIQUE or PRIMARY constraint)
    public function upsert($data, $conflictTarget) {
        return $this->db->upsertRowOnConflict($this->table, $data, $conflictTarget);
    }

    // Escape string
    public function escape($str) {
        return $this->db->escape($str);
    }
}

?>
