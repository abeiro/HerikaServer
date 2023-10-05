<?php

class sql
{
    private static $link = null;


    public function __construct()
    {
        self::$link = new SQLite3(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR ."data". DIRECTORY_SEPARATOR . "mysqlitedb.db");
        self::$link->busyTimeOut(5000);

    }

    public function __destruct()
    {
        
        self::$link->close();
    }


    public function insert($table, $data)
    {

        if ($table=="log") {
            foreach ($data as $name=>$value) {
                if ($name=="prompt")
                    $data[$name]=SQLite3::escapeString($value);
            } 
        }
        
        if ($table=="diarylog") {
            foreach ($data as $name=>$value) {
                if ($name=="content")
                    $data[$name]=SQLite3::escapeString($value);
            } 
        }
        
        if ($table=="diarylogv2") {
            foreach ($data as $name=>$value) {
                if ($name=="content")
                    $data[$name]=SQLite3::escapeString($value);
            } 
        }
        
        
        //file_put_contents("/tmp/test.sql.txt","\nINSERT INTO $table (" . implode(",", array_keys($data)) . ") VALUES ('" . implode("','", $data) . "')\n",FILE_APPEND);
        self::$link->exec("INSERT INTO $table (" . implode(",", array_keys($data)) . ") VALUES ('" . implode("','", $data) . "')");



    }


    function query($query)
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
        self::$link->exec($sqlquery);
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
