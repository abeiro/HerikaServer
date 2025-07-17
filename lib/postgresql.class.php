<?php
require_once("logger.php");

class sql
{
    private static $link = null;
    private $queryTimeThreshold = 0.5; // Time threshold in seconds

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

    public function GetLastError()
    {
        return pg_last_error(self::$link);
    }

    public function insert($table, $data)
    {
        $startTime = microtime(true);
        $i=0;
        $columns = implode(', ', array_keys($data));
        foreach (array_keys($data) as $d) {
            $values[]='$'.(++$i);
        }
        $values = implode(', ', $values);

        $query = "INSERT INTO $table ($columns) VALUES ($values)";
        $params = array_values($data);
        $result = pg_query_params(self::$link, $query, $params);
        $endTime = microtime(true);

        $elapsedTime = $endTime - $startTime;
        if ($elapsedTime > $this->queryTimeThreshold) {
            Logger::error("Insert query execution time exceeded threshold: {$elapsedTime} seconds.");
        }

        if (!$result) {
            Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
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

    public function truncate($table, $restart = false, $cascade = false)
    {
        if ($table > "") {
            $query = "TRUNCATE {$table}";
            if ($restart) 
                $query .= " RESTART IDENTITY";
            if ($cascade)
                $query .= " CASCADE";
            pg_query(self::$link, $query);
        } else
            Logger::warn("SQL::truncate empty parameter [table] ");
    }

    public function update($table, $set, $where = "FALSE")
    {
        $startTime = microtime(true);
        $query = "UPDATE $table SET $set WHERE $where";
        $result = pg_query(self::$link, $query);
        $endTime = microtime(true);

        $elapsedTime = $endTime - $startTime;
        if ($elapsedTime > $this->queryTimeThreshold) {
            Logger::error("Update query execution time exceeded threshold: {$elapsedTime} seconds.");
        }

        if (!$result) {
            Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
        }
    }

    public function execQuery($sqlquery)
    {
        $startTime = microtime(true);
        $result = pg_query(self::$link, $sqlquery);
        $endTime = microtime(true);

        $elapsedTime = $endTime - $startTime;
        if ($elapsedTime > $this->queryTimeThreshold) {
            Logger::error("Query execution time exceeded threshold: {$elapsedTime} seconds.");
        }

        if (!$result) {
            Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
        }

        return $result;
    }

    public function execQueryVerbose($sqlquery)
    {
        $startTime = microtime(true);
        $result = pg_query(self::$link, $sqlquery);
        $endTime = microtime(true);

        $elapsedTime = $endTime - $startTime;
        if ($elapsedTime > $this->queryTimeThreshold) {
            Logger::error("Query execution time exceeded threshold: {$elapsedTime} seconds.");
        }

        if (!$result) {
            $res = pg_last_error(self::$link);
            Logger::error($res . print_r(debug_backtrace(), true));
            return $res; 
        }
        return "";
    }

    public function fetchAll($q)
    {
        $startTime = microtime(true);
        $result = pg_query(self::$link, $q);
        $endTime = microtime(true);

        $elapsedTime = $endTime - $startTime;
        if ($elapsedTime > $this->queryTimeThreshold) {
            Logger::error("FetchAll query execution time exceeded threshold: {$elapsedTime} seconds.");
        }

        if (!$result) {
            Logger::error(pg_last_error(self::$link));
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
        $startTime = microtime(true);
        $result = pg_query(self::$link, $q);
        $endTime = microtime(true);

        $elapsedTime = $endTime - $startTime;
        if ($elapsedTime > $this->queryTimeThreshold) {
            Logger::error("FetchOne query execution time exceeded threshold: {$elapsedTime} seconds.");
        }

        if (!$result) {
            Logger::error(pg_last_error(self::$link));
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
            return pg_escape_string(self::$link,$string);
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
        
        $result = pg_query_params(self::$link, $query, $params);
        if (!$result) {
            Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
        }
    }

        public function upsertRow($table, $data, $where) {
            // Check if the row exists
            $checkQuery = "SELECT 1 FROM $table WHERE $where LIMIT 1";
            $checkResult = pg_query(self::$link, $checkQuery);

            if (!$checkResult) {
                Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
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
            $result = pg_query_params(self::$link, $query, $params);
            if (!$result) {
                Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
                return false;
            }

            return true;
    }

    public function upsertRowTrx($table, $data, $whereCondition) {
        // Start a transaction
        pg_query(self::$link, "BEGIN");
    
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
            $checkResult = pg_query_params(self::$link, $checkQuery, $whereParams);
    
            if (!$checkResult) {
                throw new Exception(pg_last_error(self::$link));
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
            $result = pg_query_params(self::$link, $query, $params);
            if (!$result) {
                throw new Exception(pg_last_error(self::$link));
            }
    
            // Commit transaction
            pg_query(self::$link, "COMMIT");
    
            return true;
        } catch (Exception $e) {
            // Rollback on error
            pg_query(self::$link, "ROLLBACK");
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
            return pg_escape_literal(self::$link, $value);
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
    
        $result = pg_query(self::$link, $sqlquery);
    
        if (!$result) {
            Logger::error(pg_last_error(self::$link) . print_r(debug_backtrace(), true));
            return false; // Indicate failure
        }
    
        return true; // Indicate success
    }

    /*
     * Class Method Summary:
     * __construct: Establishes a database connection using pg_connect.
     * __destruct: Closes the database connection when the object is destroyed.
     * close: Closes the database connection.
     * GetLastError: Returns the last error message from the database.
     * insert: Inserts data into a specified table.
     * query: Executes a raw SQL query.
     * delete: Deletes rows from a table based on a WHERE clause.
     * truncate: Truncates a table, optionally restarting identity and cascading.
     * update: Updates data in a table based on a WHERE clause.
     * execQuery: Executes a SQL query and returns the result.
     * execQueryVerbose: Executes a SQL query and returns either an empty string on success or the error message on failure.
     * fetchAll: Executes a query and returns all rows as an associative array.
     * fetchOne: Executes a query and returns the first row as an associative array.
     * fetchArray: Fetches a row as an array.
     * escape: Escapes a string for use in a SQL query.
     * updateRow: Updates a row in a table using parameterized queries.
     * upsertRow: Inserts or updates a row in a table based on whether it exists.
     * upsertRowTrx: Performs an upsert operation within a transaction for data integrity.
     * upsertRowOnConflict: Performs an upsert based on an existing index constraint.
     */

}
?>