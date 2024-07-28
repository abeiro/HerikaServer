<?php

class sql
{
    private static $link = null;

    public function __construct()
    {
        $connString = "host=localhost dbname=dwemer user=dwemer password=dwemer";
        self::$link = pg_connect($connString);
        if (!self::$link) {
            die("Error in connection: " . pg_last_error());
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if (self::$link) {
            pg_close(self::$link);
            self::$link = null;
        }
    }

    public function insert($table, $data)
    {
		$i=0;
        $columns = implode(', ', array_keys($data));
		foreach (array_keys($data) as $d) {
			$values[]='$'.(++$i);
		}
        $values = implode(', ', $values);

        $query = "INSERT INTO $table ($columns) VALUES ($values)";
		//error_log($query);
        $params = array_values($data);

        $result = pg_query_params(self::$link, $query, $params);
        if (!$result) {
            error_log(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
        }
    }

    public function query($query)
    {
        return pg_query(self::$link, $query);
    }

    public function delete($table, $where = "FALSE")
    {
        $query = "DELETE FROM $table WHERE $where";
        pg_query(self::$link, $query);
    }

    public function update($table, $set, $where = "FALSE")
    {
        $query = "UPDATE $table SET $set WHERE $where";
        pg_query(self::$link, $query);
    }

    public function execQuery($sqlquery)
    {
        $result = pg_query(self::$link, $sqlquery);
        if (!$result) {
            error_log(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
        }
    }

    public function fetchAll($q)
    {
        $result = pg_query(self::$link, $q);
        if (!$result) {
            error_log(pg_last_error(self::$link));
            return [];
        }

        $finalData = array();
        while ($row = pg_fetch_assoc($result)) {
            $finalData[] = $row;
        }
        

        return $finalData;
    }
    
    public function fetchArray($res)
    {
        return pg_fetch_array($res);
    }
 
    public function updateRow($table, $data, $where)
    {
        $setClauses = [];
        $params = [];
        $i = 0;

        foreach ($data as $column => $value) {
            $setClauses[] = "$column = $" . (++$i);
            $params[] = $value;
        }

        $set = implode(', ', $setClauses);

        $query = "UPDATE $table SET $set WHERE $where";
        
        $result = pg_query_params(self::$link, $query, $params);
        if (!$result) {
            error_log(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
        }
    }
}

?>
