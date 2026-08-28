<?php
require_once("logger.php");

class sql
{
    private static $link = null;
    private $queryTimeThreshold = 0.5; // Time threshold in seconds
    private $connString = "host=localhost dbname=dwemer user=dwemer password=dwemer connect_timeout=90"; 
    private $debug_level = 3; // 0 = quiet .. 3=use timer .. 5 = verbose
    
    public function __construct()
    {
        //$connString = "host=localhost dbname=dwemer user=dwemer password=dwemer connect_timeout=15";
        self::$link = @pg_connect($this->connString);

        if (!self::$link || self::$link === false) {
            Logger::error("SQL: connection init failed. " . $this->extract_caller() );
            die("SQL: Error in connection.");
        }

        $stat = pg_connection_status(self::$link);
        if ((!isset($stat)) || ($stat !== PGSQL_CONNECTION_OK)) {
            Logger::error("SQL: connection init FAILED [$stat] " . $this->extract_caller() );
            die("SQL: Error in connection.");
        }         
        
        // Ensure consistent schema resolution across sessions
        pg_query(self::$link, "SET search_path TO public");
        if ($this->debug_level > 4)
            Logger::debug("SQL: connected $stat to ". pg_host(self::$link) ."/". pg_dbname(self::$link) . " " . $this->extract_caller() . $this->GetLastError() );
    }

    private function re_connect() { //check connection availability and reconnect if connection expired
        $b_ok = true;

        if (!isset(self::$link)) { // connection not inited or closed
            if ($this->debug_level > 3)
                Logger::debug("SQL: disconnected, retry. " . $this->GetLastError() . $this->extract_caller() );
            $b_ok = false;
        } else { // connection inited, check if is OK to use
            $stat = pg_connection_status(self::$link);
            if ((!isset($stat)) || ($stat !== PGSQL_CONNECTION_OK)) {
                $b_ok = false;
                if ($this->debug_level > 3)
                    Logger::error("SQL: connection not OK, retry. code={$stat}. " . $this->extract_caller() );
            }         
        }
        
        if (!$b_ok) { //reconnect
            self::$link = pg_connect($this->connString);

            if (!isset(self::$link)) {
                Logger::error("SQL: connection retry failed! " . $this->extract_caller() );
                die("SQL: Error in connection.");
            }

            $stat = pg_connection_status(self::$link);
            if ((!isset($stat)) || ($stat !== PGSQL_CONNECTION_OK)) {
                Logger::error("SQL: connection retry FAILED with code={$stat}. " . $this->extract_caller() );
                die("SQL: bad connection.");
            }         
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if (self::$link) {
            if ($this->debug_level > 4)
                Logger::debug("SQL: close connection to ". pg_host(self::$link) ."/". pg_dbname(self::$link) . " " . $this->extract_caller() );
            pg_close(self::$link);
            self::$link = null;
        } else
            if ($this->debug_level > 4)
                Logger::debug("SQL: close connection [null]. " . $this->extract_caller() . $this->GetLastError() );
    }

    public function set_debug_level($debug_lvl = 0) { // set debug level, 0=quiet 5=max verbosity, 0 doesn't suppress errors
        $this->debug_level = $debug_lvl;
    }
    
    private function extract_caller() { // format back trace output
        $s_res = "";
        if ($this->debug_level > 0) {
            $arr_bkt = debug_backtrace();
            if (isset($arr_bkt) && (count($arr_bkt)> 0)) {
                $s_res .= " stack: ";
                for ($i = 1; $i <= 4; $i++) {
                    $s_file = $arr_bkt[$i]['file'] ?? '';
                    $s_line = $arr_bkt[$i]['line'] ?? '';
                    $s_func = $arr_bkt[$i]['function'] ?? '';
                    $s_class = $arr_bkt[$i]['class'] ?? '';
                    if (isset($arr_bkt[$i]['args']) && (count($arr_bkt[$i]['args']) > 0)) {
                        $s_arg = json_encode($arr_bkt[$i]['args'],JSON_UNESCAPED_SLASHES); //"array[".count($arr_bkt[$i]['args'][0])."]";
                    } else $s_arg = '';
                    $sx = $s_file.$s_line.$s_func;
                    if (strlen($sx) > 0) {
                        if ($s_line > '')
                            $s_line = "[$s_line]";
                        if ($s_func > '')
                            $s_func = "s_func";
                        if ($s_class > '')
                            $s_class = "$s_class::";
                        if ($s_arg > '')
                            $s_arg = "($s_arg)";
                        else 
                            $s_arg = "()";
                        $s_res .= "#{$i} {$s_file} {$s_line} {$s_class}{$s_func}{$s_arg}. ";
                    }
                }
            }
        }
        return $s_res;
    }
    
    public function GetLastError()
    {
        if (self::$link)     
            return pg_last_error(self::$link)." ";
        else 
            return "SQL: connection not initialized. ";
    }

    public function insert($table, $data)
    {
        $startTime = microtime(true);
        $this->re_connect();
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
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: Insert query execution time exceeded threshold {$elapsedTime} seconds. {$query} ". $this->extract_caller() );
            }
        }
        if (!$result) {
            Logger::error("SQL: Insert query failed {$query} " . $this->GetLastError() . $this->extract_caller() );
        }
    }

    public function insertReturningId($table, $data, $idColumn = 'id')
    {
        $startTime = microtime(true);
        $this->re_connect();

        foreach (array_merge([$table, $idColumn], array_keys($data)) as $identifier) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)$identifier)) {
                Logger::error("SQL: Invalid identifier for insertReturningId");
                return 0;
            }
        }

        $values = [];
        foreach (array_keys($data) as $index => $column) {
            $values[] = '$' . ($index + 1);
        }

        $columns = implode(', ', array_keys($data));
        $query = "INSERT INTO {$table} ({$columns}) VALUES (" . implode(', ', $values) . ") RETURNING {$idColumn}";
        $result = pg_query_params(self::$link, $query, array_values($data));

        $elapsedTime = microtime(true) - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;

        if ($this->debug_level > 2 && $elapsedTime > $this->queryTimeThreshold) {
            Logger::warn("SQL: Insert query execution time exceeded threshold {$elapsedTime} seconds. {$query} " . $this->extract_caller());
        }
        if (!$result) {
            Logger::error("SQL: Insert query failed {$query} " . $this->GetLastError() . $this->extract_caller());
            return 0;
        }

        $row = pg_fetch_assoc($result);
        return isset($row[$idColumn]) ? (int)$row[$idColumn] : 0;
    }

    public function query($query)
    {
        $startTime = microtime(true);
        $this->re_connect();

        if (!function_exists('pg_query')) {
            Logger::error("SQL: pg_query function not available. Ensure PHP pgsql extension is installed. " . $this->extract_caller());
            return false;
        }

        $result = pg_query(self::$link, $query);
        
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: query execution time exceeded threshold {$elapsedTime} seconds. {$query} " . $this->extract_caller());
            }
        }
        if ($result === false) {
            Logger::error("SQL: query failed {$query} " . $this->GetLastError() . $this->extract_caller());
        }
        return $result;
    }

    public function delete($table, $where = "FALSE")
    {
        $startTime = microtime(true);
        $this->re_connect();
        $query = "DELETE FROM $table WHERE $where";
        $result = pg_query(self::$link, $query);
        
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: delete execution time exceeded threshold {$elapsedTime} seconds. {$query} " . $this->extract_caller());
            }
        }
        if ($result === false) {
            $error = pg_last_error(self::$link);
            error_log("[DB_DELETE] Failed to delete from {$table} WHERE {$where}. Error: {$error}");
        }
        return $result !== false;
    }

    public function truncate($table, $restart = false, $cascade = false)
    {
        if ($table > "") {
            $startTime = microtime(true);
            $this->re_connect();
            $query = "TRUNCATE {$table}";
            if ($restart) 
                $query .= " RESTART IDENTITY";
            if ($cascade)
                $query .= " CASCADE";
            pg_query(self::$link, $query);
            
            $endTime = microtime(true);
            $elapsedTime = $endTime - $startTime;
            if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
                $GLOBALS["DB_EXECUTION_TIME"] = 0;
            }
            $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
            
            if ($this->debug_level > 2) {
                if ($elapsedTime > $this->queryTimeThreshold) {
                    Logger::warn("SQL: truncate execution time exceeded threshold {$elapsedTime} seconds. {$query} " . $this->extract_caller());
                }
            }
        } else
            Logger::warn("SQL: truncate empty parameter [table] " . $this->extract_caller() );
    }

    public function update($table, $set, $where = "FALSE")
    {
        $startTime = microtime(true);
        $this->re_connect();
        $query = "UPDATE $table SET $set WHERE $where";
        $result = pg_query(self::$link, $query);

        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: update query execution time exceeded threshold: {$elapsedTime} seconds. {$query} ");
            }
        }
        if (!$result) {
            Logger::error("SQL: update query failed {$query} " . $this->GetLastError() . $this->extract_caller() );
        }
    }

    public function execQuery($sqlquery)
    {
        $startTime = microtime(true);
        $this->re_connect();
        $result = pg_query(self::$link, $sqlquery);

        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: query execution time exceeded threshold: {$elapsedTime} seconds. {$sqlquery} ");
            }
        }
        if (!$result) {
            Logger::error("SQL: execute query failed {$sqlquery} " . $this->GetLastError() . $this->extract_caller() );
        }

        return $result;
    }

    public function execQueryVerbose($sqlquery)
    {
        $startTime = microtime(true);
        $this->re_connect();
        $result = pg_query(self::$link, $sqlquery);

        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::error("Query execution time exceeded threshold: {$elapsedTime} seconds. {$sqlquery} ");
            }
        }
        if (!$result) {
            $res = $this->GetLastError();
            Logger::error("SQL: execute query failed {$sqlquery} " . $res . $this->extract_caller() );
            return $res; 
        }
        return "";
    }

    public function fetchAll($q,$log=false)
    {
        $startTime = microtime(true);
        $this->re_connect();
        $result = pg_query(self::$link, $q);

        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: FetchAll execution time exceeded threshold: {$elapsedTime} seconds. '{$q}' ");
            }
        }
        
        if ($log) {
            if ($this->debug_level > 0)
                error_log($q);
        }

        if (!isset($result)) {
            Logger::error("SQL: FetchAll failed '{$q}' " . $this->GetLastError() . $this->extract_caller() );
            return [];
        }

        $finalData = array();
        
        if (!$result) {
            throw new Exception("SQL: FetchAll query failed '{$q}' " . $this->GetLastError() . $this->extract_caller());
        }

        while ($row = pg_fetch_assoc($result)) {
            $finalData[] = $row;
        }

        return $finalData;
    }

    public function fetchOne($q)
    {
        $startTime = microtime(true);
        $this->re_connect();
        $result = pg_query(self::$link, $q);
        // error_log($q);
        
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::error("SQL: FetchOne query execution time exceeded threshold: {$elapsedTime} seconds. {$q} ");
            }
        }
        if (!$result) {
            Logger::error("SQL: FetchOne failed {$q} " . $this->GetLastError() . $this->extract_caller() );
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
        $this->re_connect();
        return pg_fetch_array($res);
    }
 
    public function escape($string)
    {
        if ($string !== null) {
            $this->re_connect();
            return pg_escape_string(self::$link, strval($string));
        } else
            return "";
    }

    public function escapeLiteral($string)
    {
        if ($string !== null) {
            $this->re_connect();
            return pg_escape_literal(self::$link, strval($string));
        } else
            return "";
    }

    public function updateRow($table, $data, $where, $requireAffectedRow = false)
    {
        $startTime = microtime(true);
        $setClauses = [];
        $params = [];
        $i = 0;

        foreach ($data as $column => $value) {
            $setClauses[] = "$column = $" . (++$i);
            $params[] = $value;
        }

        $set = implode(', ', $setClauses);

        $query = "UPDATE $table SET $set WHERE $where";
        $this->re_connect();
        $result = pg_query_params(self::$link, $query, $params);
        
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: updateRow execution time exceeded threshold: {$elapsedTime} seconds. {$query} " . $this->extract_caller());
            }
        }
        if (!$result) {
            Logger::error("SQL: updateRow failed {$query} " .$this->GetLastError() . $this->extract_caller() );
            return false;
        }
        // Optimistic identity/profile guards must not report success when a concurrent write won.
        return !$requireAffectedRow || pg_affected_rows($result) > 0;
    }

    public function upsertRow($table, $data, $where) {
        $startTime = microtime(true);
        // Check if the row exists
        $this->re_connect();
        $checkQuery = "SELECT 1 FROM $table WHERE $where LIMIT 1";
        $checkResult = pg_query(self::$link, $checkQuery);

        if (!$checkResult) {
            Logger::error("SQL: upsertRow failed {$checkQuery} " . $this->GetLastError() . $this->extract_caller() );
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
        
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: upsertRow execution time exceeded threshold: {$elapsedTime} seconds. {$query} " . $this->extract_caller());
            }
        }
        if (!$result) {
            Logger::error("SQL: upsertRow failed {$query} " . $this->GetLastError() . $this->extract_caller() );
            return false;
        }

        return true;
    }

    public function upsertRowTrx($table, $data, $whereCondition) {
        $startTime = microtime(true);
        $query = "";
        // Start a transaction
        $this->re_connect();
        pg_query(self::$link, "BEGIN");
    
        try {
            // Extract WHERE condition keys and values
            $whereClauses = [];
            $whereParams = [];
            $i = 0;
    
            foreach ($whereCondition as $column => $value) {
                $whereClauses[] = "$column = $" . (++$i);
                $whereParams[] = $value;
            }
    
            
            $whereSql = implode(" AND ", $whereClauses);
    
            // Check if the row exists and lock it
            $checkQuery = "SELECT 1 FROM $table WHERE $whereSql FOR UPDATE";
            $checkResult = pg_query_params(self::$link, $checkQuery, $whereParams);
    
            if (!$checkResult) {
                throw new Exception("SQL: upsertRowTrx failed {$checkQuery} " . $this->GetLastError() . $this->extract_caller() );
            }
    
            if (pg_num_rows($checkResult) > 0) {
                // Row exists, perform an UPDATE (excluding WHERE fields)
                $setClauses = [];
                // Parameter positions $1..$n are reserved for WHERE values.
                $params = $whereParams;
                
                foreach ($data as $column => $value) {
                    if (!array_key_exists($column, $whereCondition)) { // Exclude WHERE fields
                        $setClauses[] = "$column = $" . (++$i);
                        $params[] = $value;
                    }
                }
    
                if (empty($setClauses)) {
                    throw new Exception("SQL: upsertRowTrx failed, no update-able fields provided. " . $this->extract_caller() );
                }
    
                $setSql = implode(', ', $setClauses);
                $query = "UPDATE $table SET $setSql WHERE $whereSql";
            } else {
                // Row does not exist, perform an INSERT
                $i = 0;
                $columns = array_keys($data);
                $placeholders = [];
                $params = [];
    
                foreach ($data as $index => $value) {
                    $placeholders[] = '$' . (++$i) ;
                    $params[] = $value;
                }
    
                $columnList = implode(', ', $columns);
                $placeholderList = implode(', ', $placeholders);
                $query = "INSERT INTO $table ($columnList) VALUES ($placeholderList)";
            }
    
            // error_log($query . " " . print_r($params, true));
    
            // Execute the query
            $result = pg_query_params(self::$link, $query, $params);
            if (!$result) {
                throw new Exception("SQL: upsertRowTrx failed {$query} " . $this->GetLastError() . $this->extract_caller() );
            }
    
            // Commit transaction
            pg_query(self::$link, "COMMIT");
    
            $endTime = microtime(true);
            $elapsedTime = $endTime - $startTime;
            if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
                $GLOBALS["DB_EXECUTION_TIME"] = 0;
            }
            $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
            
            if ($this->debug_level > 2) {
                if ($elapsedTime > $this->queryTimeThreshold) {
                    Logger::warn("SQL: upsertRowTrx execution time exceeded threshold: {$elapsedTime} seconds. " . $this->extract_caller());
                }
            }
            
            return true;
        } catch (Exception $e) {
            // Rollback on error
            pg_query(self::$link, "ROLLBACK");
            
            $endTime = microtime(true);
            $elapsedTime = $endTime - $startTime;
            if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
                $GLOBALS["DB_EXECUTION_TIME"] = 0;
            }
            $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
            
            Logger::error("SQL: upsertRowTrx failed {$query} " . $e->getMessage() . $this->extract_caller() );
            return false;
        }
    }

    // an upsert that completes in one query. Good for simple cases when a constraint can be used
    public function upsertRowOnConflict($tableName, $data, $conflictTarget) {
        $startTime = microtime(true);
        // Prepare the column names for the INSERT statement.

        $columns = implode(', ', array_keys($data));
    
        $this->re_connect();

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
    
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        if (!isset($GLOBALS["DB_EXECUTION_TIME"])) {
            $GLOBALS["DB_EXECUTION_TIME"] = 0;
        }
        $GLOBALS["DB_EXECUTION_TIME"] += $elapsedTime;
        
        if ($this->debug_level > 2) {
            if ($elapsedTime > $this->queryTimeThreshold) {
                Logger::warn("SQL: upsertRowOnConflict execution time exceeded threshold: {$elapsedTime} seconds. {$sqlquery} " . $this->extract_caller());
            }
        }
        if (!$result) {
            Logger::error("SQL: upsertRowOnConflict failed {$sqlquery} " . $this->GetLastError() . $this->extract_caller() );
            return false; // Indicate failure
        }
    
        return true; // Indicate success
    }

    /*
     * Class Method Summary:
     * Note. Singleton object is available on $GLOBALS["db].
     * __construct(): Establishes a database connection using pg_connect.
     * __destruct(): Closes the database connection when the object is destroyed.
     * close(): Closes the database connection.
     * re_connect(): check connection availability and reconnect if connection expired
     * GetLastError(): Returns the last error message from the database.
     * set_debug_level($debug_lvl = 0): set debug level, 0=quiet 5=max verbosity, doesn't suppress errors
     * extract_caller() // format back trace output     
     * insert($table, $data): Inserts data into a specified table.
     * query($query): Executes a raw SQL query.
     * delete($table, $where = "FALSE"): Deletes rows from a table based on a WHERE clause.
     * truncate($table, $restart = false, $cascade = false): Truncates a table, optionally restarting identity and cascading.
     * update($table, $set, $where = "FALSE"): Updates data in a table based on a WHERE clause.
     * execQuery($sqlquery): Executes a SQL query and returns the result.
     * execQueryVerbose($sqlquery): Executes a SQL query and returns either an empty string on success or the error message on failure.
     * fetchAll($q): Executes a query and returns all rows as an associative array.
     * fetchOne($q): Executes a query and returns the first row as an associative array.
     * fetchArray($res): Fetches a row as an array.
     * escape($string): Escapes a string for use in a SQL query.
     * updateRow($table, $data, $where): Updates a row in a table using parameterized queries.
     * upsertRow($table, $data, $where): Inserts or updates a row in a table based on whether it exists.
     * upsertRowTrx($table, $data, $whereCondition): Performs an upsert operation within a transaction for data integrity.
     * upsertRowOnConflict($tableName, $data, $conflictTarget): Performs an upsert based on an existing index constraint.
     */

}
?>
