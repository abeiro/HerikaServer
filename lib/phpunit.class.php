<?php

class sql
{
    private $link = null;

    public function __construct()
    {
        $connString = getenv('HERIKA_TEST_DATABASE_DSN') ?: "host=localhost dbname=testdb user=dwemer password=dwemer";
        $this->link = pg_connect($connString, PGSQL_CONNECT_FORCE_NEW);
        if (!$this->link) {
            die("Error in connection: " . pg_last_error());
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->link) {
            pg_close($this->link);
            $this->link = null;
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
        //error_log(print_r($params,true));
        $result = pg_query_params($this->link, $query, $params);
        if (!$result) {
            Logger::error(pg_last_error($this->link) . print_r(debug_backtrace(), true));
        }
    }

    public function query($query)
    {
        return pg_query($this->link, $query);
    }

    public function delete($table, $where = "FALSE")
    {
        $query = "DELETE FROM $table WHERE $where";
        pg_query($this->link, $query);
    }

    public function update($table, $set, $where = "FALSE")
    {
        $query = "UPDATE $table SET $set WHERE $where";
        pg_query($this->link, $query);
    }

    public function execQuery($sqlquery)
    {
        $result = pg_query($this->link, $sqlquery);
        if (!$result) {
            Logger::error(pg_last_error($this->link) . print_r(debug_backtrace(), true));
        }
        return $result;
    }

    public function fetchAll($q)
    {
        $result = pg_query($this->link, $q);
        if (!$result) {
            Logger::error(pg_last_error($this->link));
            return [];
        }

        $finalData = array();
        while ($row = pg_fetch_assoc($result)) {
            $finalData[] = $row;
        }
        

        return $finalData;
    }
    
    public function fetchOne($q)
    {
        $result = pg_query($this->link, $q);
        if (!$result) {
            Logger::error(pg_last_error($this->link));
            return [];
        }

        $finalData = array();
        while ($row = pg_fetch_assoc($result)) {
            $finalData = $row;
            break;
        }
        

        return $finalData;
    }

    public function fetchArray($res)
    {
        return pg_fetch_array($res);
    }
 
    public function escape($string)
    {
        if ($string)
            return pg_escape_string($this->link,$string);
        else
            return "";
    }

    public function escapeLiteral($string)
    {
        if ($string !== null)
            return pg_escape_literal($this->link, strval($string));
        else
            return "";
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
        
        $result = pg_query_params($this->link, $query, $params);
        if (!$result) {
            Logger::error(pg_last_error($this->link) . print_r(debug_backtrace(), true));
        }
        return $result !== false;
    }

    public function upsertRow($table, $data, $where) {
        // Check if the row exists
        $checkQuery = "SELECT 1 FROM $table WHERE $where LIMIT 1";
        $checkResult = pg_query($this->link, $checkQuery);

        if (!$checkResult) {
            Logger::error(pg_last_error($this->link) . print_r(debug_backtrace(), true));
            return false;
        }

        if (pg_num_rows($checkResult) > 0) {
            // Row exists, perform an update
            $setClauses = [];
            $params = [];
            $i = 0;

            foreach ($data as $column => $value) {
                $setClauses[] = "$column = $" . (++$i);
                $params[] = $value;
            }

            $set = implode(', ', $setClauses);
            $query = "UPDATE $table SET $set WHERE $where";
        } else {
            // Row does not exist, perform an insert
            $columns = array_keys($data);
            $placeholders = [];
            $params = [];
            $i = 0;

            foreach ($data as $value) {
                $placeholders[] = '$' . (++$i);
                $params[] = $value;
            }

            $columnList = implode(', ', $columns);
            $placeholderList = implode(', ', $placeholders);

            $query = "INSERT INTO $table ($columnList) VALUES ($placeholderList)";
        }

        // Execute the query
        $result = pg_query_params($this->link, $query, $params);
        if (!$result) {
            Logger::error(pg_last_error($this->link) . print_r(debug_backtrace(), true));
            return false;
        }

        return true;
    }

    public function upsertRowTrx($table, $data, $whereCondition) {
        // Start a transaction
        pg_query($this->link, "BEGIN");
    
        try {
            // Extract WHERE condition keys and values
            $whereClauses = [];
            $whereParams = [];
            $i = 0;
    
            foreach ($whereCondition as $column => $value) {
                $whereClauses[] = "$column = $" . (++$i); // Explicitly cast as TEXT
                $whereParams[] = (string) $value; // Convert value to string
            }
    
            
            $whereSql = implode(" AND ", $whereClauses);
    
            // Check if the row exists and lock it
            $checkQuery = "SELECT 1 FROM $table WHERE $whereSql FOR UPDATE";
            $checkResult = pg_query_params($this->link, $checkQuery, $whereParams);
    
            if (!$checkResult) {
                throw new Exception(pg_last_error($this->link));
            }
    
            if (pg_num_rows($checkResult) > 0) {
                // Row exists, perform an UPDATE (excluding WHERE fields)
                $setClauses = [];
                $params = [];
                foreach ($data as $column => $value) {
                    if (!array_key_exists($column, $whereCondition)) { // Exclude WHERE fields
                        $setClauses[] = "$column = $" . (++$i); // Explicitly cast as TEXT
                        $params[] = (string) $value; // Convert value to string
                    }
                }
    
                if (empty($setClauses)) {
                    throw new Exception("No updatable fields provided.");
                }
    
                $setSql = implode(', ', $setClauses);
                $query = "UPDATE $table SET $setSql WHERE $whereSql";
    
                // Merge params: first the update values, then the WHERE values
                $params = array_merge($params, $whereParams);
            } else {
                // Row does not exist, perform an INSERT
                $i = 0;
                $columns = array_keys($data);
                $placeholders = [];
                $params = [];
    
                foreach ($data as $index => $value) {
                    $placeholders[] = '$' . (++$i) ; // Explicitly cast as TEXT
                    $params[] = (string) $value; // Convert value to string
                }
    
                $columnList = implode(', ', $columns);
                $placeholderList = implode(', ', $placeholders);
                $query = "INSERT INTO $table ($columnList) VALUES ($placeholderList)";
            }
    
            // error_log($query . " " . print_r($params, true));
    
            // Execute the query
            $result = pg_query_params($this->link, $query, $params);
            if (!$result) {
                throw new Exception(pg_last_error($this->link));
            }
    
            // Commit transaction
            pg_query($this->link, "COMMIT");
    
            return true;
        } catch (Exception $e) {
            // Rollback on error
            pg_query($this->link, "ROLLBACK");
            Logger::error($e->getMessage() . print_r(debug_backtrace(), true));
            return false;
        }
    }

    // an upsert that completes in one query. Good for simple cases when a constraint can be used
    public function upsertRowOnConflict($tableName, $data, $conflictTarget) {
        // Prepare the column names for the INSERT statement.
        $columns = implode(', ', array_keys($data));
    
        // Take care of escaping here instead of requiring it before every upsert call
        $values = array_map(function($value) {
            return pg_escape_literal($this->link, $value);
        }, array_values($data));
        $valuesString = implode(', ', $values);
    
        // EXCLUDED refers to the row that was attempted to be inserted.
        // This loop constructs "column = EXCLUDED.column" for each column in the data.
        $updateStatements = [];
        foreach ($data as $column => $value) {
            $updateStatements[] = "$column = EXCLUDED.$column";
        }
        $updateString = implode(', ', $updateStatements);
    
        // ON CONFLICT ... DO UPDATE is effectively an upsert
        // If the constraint in $conflictTarget is violated during the insert, an update will be done instead
        $sqlquery = "INSERT INTO $tableName ($columns) VALUES ($valuesString) " .
                    "ON CONFLICT ($conflictTarget) DO UPDATE SET $updateString;";
    
        $result = pg_query($this->link, $sqlquery);
    
        if (!$result) {
            Logger::error(pg_last_error($this->link) . print_r(debug_backtrace(), true));
            return false; // Indicate failure
        }
    
        return true; // Indicate success
    }

}

?>
