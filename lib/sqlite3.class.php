<?php

class sql
{
    private static $link = null;


    public function __construct()
    {
        self::$link = new SQLite3(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR ."data". DIRECTORY_SEPARATOR . "mysqlitedb.db");
        self::$link->busyTimeOut(10000);

    }

    public function __destruct()
    {

        self::$link->close();
    }


    public function close()
    {

        self::$link->close();
    }

    public function insert($table, $data)
    {
       

        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));


        $query = "INSERT INTO $table ($columns) VALUES ($values)";


        // Prepare the SQL query
        $stmt = self::$link->prepare($query);

        // Bind the values from the array to the placeholders
        $n=1;
        foreach ($data as $key => $value) {
            if (in_array($key, ["gamets","localts","input_tokens","total_tokens_so_far","output_tokens","gamets_truncated","n"])) {
                $type=SQLITE3_INTEGER;
            } else {
                $type=SQLITE3_TEXT;
            }


            $stmt->bindValue($n, strtr($value, ["''"=>"'"]), $type);
            $n++;
        }

        //file_put_contents("/tmp/test.sql.txt","\n".print_r($stmt->getSQL(true))."\n",FILE_APPEND);
        // Execute the prepared statement
        $result = $stmt->execute();
        if (!$result) {
            error_log(self::$link->lastErrorMsg().debug_backtrace());

        }

    }


    public function query($query)
    {
        return self::$link->query($query);
    }

    public function delete($table, $where = " false ")
    {
        self::$link->exec("DELETE FROM  $table WHERE $where");
    }

    public function update($table, $set, $where = " false ")
    {
        self::$link->exec("UPDATE  $table set $set WHERE $where");
    }

    public function execQuery($sqlquery)
    {
        $result=self::$link->exec($sqlquery);
        if (!$result) {
            error_log(self::$link->lastErrorMsg().print_r(debug_backtrace(), true));
        }
    }


    public function fetchAll($q)
    {

        $results = self::$link->query("$q");
        $finalData = array();
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $finalData[] = $row;
        }

        return $finalData;

    }



}
