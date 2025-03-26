<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

abstract class DatabaseTestCase extends TestCase
{
    protected static string $testDatabaseName = "testdb";
    protected static string $testDatabaseBkpName = "testdb_bkp";
    protected string $testNPCName = "Unit Test";

    public static function setUpBeforeClass(): void
    {
        self::createTestDB();
    }

    public static function tearDownAfterClass(): void
    {
        self::tearDownDB();
    }

    public function setUp(): void
    {
        $this->copyTestDB();
        $this->setUpDefaultMinimeMocks();
        $this->setUpDefaultConnectorMocks();
        $this->setUpConfFile();
    }

    public function tearDown(): void
    {
        $this->tearDownConfFile();
    }

    public static function createTestDB(): void
    {
        // Connect to the main database
        $connString = "host=localhost dbname=dwemer user=dwemer password=dwemer";
        $mainConnection = pg_connect($connString);
        if (!$mainConnection) {
            $this->fail("Failed to connect to main database.");
        }

        // Drop the test database if it already exists
        $dropResult = pg_query($mainConnection, "DROP DATABASE IF EXISTS ".self::$testDatabaseName." WITH (FORCE)");
        if (!$dropResult) {
            $this->fail("Failed to drop test database: " . pg_last_error($mainConnection));
        }
        $dropResult = pg_query($mainConnection, "DROP DATABASE IF EXISTS ".self::$testDatabaseBkpName." WITH (FORCE)");
        if (!$dropResult) {
            $this->fail("Failed to drop test database: " . pg_last_error($mainConnection));
        }

        // Create the test database
        $createResult = pg_query($mainConnection, "CREATE DATABASE ".self::$testDatabaseName);
        if (!$createResult) {
            $this->fail("Failed to create test database: " . pg_last_error($mainConnection));
        }

        pg_close($mainConnection);

        // Connect to the new test database
        $connString = "host=localhost dbname=".self::$testDatabaseName." user=dwemer password=dwemer";
        $testConnection = pg_connect($connString);
        // Drop and recreate database
        $Q[]="DROP SCHEMA IF EXISTS public CASCADE";
        $Q[]="DROP EXTENSION IF EXISTS vector CASCADE";
        $Q[]="CREATE SCHEMA public";
        $Q[]="CREATE EXTENSION vector";
        foreach ($Q as $QS) {
            $r = pg_query($testConnection, $QS);
        }
        pg_close($testConnection);

        // Command to import SQL file using psql
        $path = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..";
        $sqlFile = $path.DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."database_default.sql";
        $psqlCommand = "PGPASSWORD=dwemer psql -h localhost -p 5432 -U dwemer -d ".self::$testDatabaseName." -f $sqlFile";

        // Execute psql command
        $output = [];
        $returnVar = 0;
        exec($psqlCommand, $output, $returnVar);

        require_once($path.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."phpunit.class.php");

        // apply database updates
        $db = new sql();
        $GLOBALS["db"]=$db;
        require($path.DIRECTORY_SEPARATOR."debug".DIRECTORY_SEPARATOR."db_updates.php");

        // if minAI is installed then create its database tables as well, to avoid errors
        if (is_dir(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR."minai_plugin")) {
            $GLOBALS["PLAYER_NAME"]="Prisoner";
            $GLOBALS["HERIKA_NAME"]="Unit Test";
            require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR."minai_plugin".DIRECTORY_SEPARATOR."importDataToDB.php");
            require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR."minai_plugin".DIRECTORY_SEPARATOR."customintegrations.php");
            unset($GLOBALS["PLAYER_NAME"]);
            unset($GLOBALS["HERIKA_NAME"]);

            DropThreadsTableIfExists();
            InitiateDBTables();
            importXPersonalities();
            importScenesDescriptions();
        }

        $db->close();
        unset($db);
        unset($GLOBALS["db"]);

        // Connect to the new test database
        $connString = "host=localhost dbname=".self::$testDatabaseName." user=dwemer password=dwemer";
        $testConnection = pg_connect($connString);
        // Copy the test database to a backup for reuse
        $createResult = pg_query($testConnection, "CREATE DATABASE ".self::$testDatabaseBkpName." WITH TEMPLATE ".self::$testDatabaseName);
        if (!$dropResult) {
            $this->fail("Failed to copy test database: " . pg_last_error($testConnection));
        }
        pg_close($testConnection);
    }

    public function copyTestDB(): void
    {
        // Connect to the test backup database
        $connString = "host=localhost dbname=".self::$testDatabaseBkpName." user=dwemer password=dwemer";
        $testConnection = pg_connect($connString);
        if (!$testConnection) {
            $this->fail("Failed to connect to test backup database.");
        }

        // Drop the test database if it already exists
        $dropResult = pg_query($testConnection, "DROP DATABASE IF EXISTS ".self::$testDatabaseName." WITH (FORCE)");
        if (!$dropResult) {
            $this->fail("Failed to drop test database: " . pg_last_error($testConnection));
        }

        // Create the test database
        $createResult = pg_query($testConnection, "CREATE DATABASE ".self::$testDatabaseName." TEMPLATE ".self::$testDatabaseBkpName);
        if (!$createResult) {
            $this->fail("Failed to copy test database: " . pg_last_error($testConnection));
        }

        pg_close($testConnection);
    }

    public function setUpDefaultMinimeMocks() {
        // mock minime
        $GLOBALS["mockMinimeCommand"] = function($text) {
            return "null";
        };
        $GLOBALS["mockMinimeExtract"] = function($text) {
            return '{"is_memory_recall": "No", "elapsed_time": "0.05 seconds"}';
        };
        $GLOBALS["mockMinimePostTopic"] = function($text) {
            return "null";
        };
        $GLOBALS["mockMinimeTask"] = function($text) {
            return "null";
        };
        $GLOBALS["mockMinimeTopic"] = function($text) {
            return '{"input_text": "'.$text.'", "generated_tags": "'.$text.'", "elapsed_time": "0.05 seconds"}';
        };
    }

    public function setUpDefaultConnectorMocks() {
        // mock connector response
        $GLOBALS["mockConnectorSend"] = function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"The Narrator\", \"listener\": \"Prisoner\", \"message\": \"Unit test message\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        };
        $GLOBALS["mockConnectorResponseMetaData"] = function() {
            return ["wrapper_data" => ["HTTP/1.1 200 OK"]];
        };
    }

    public function setUpConfFile() {
        $md5name = md5($this->testNPCName);
        $this->tearDownConfFile();
        copy(__DIR__.DIRECTORY_SEPARATOR."conf_empty.php", __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR."conf_{$md5name}.php");
    }

    public static function tearDownDB(): void
    {
        if (isset($GLOBALS["db"])) {
            $GLOBALS["db"]->close();
            unset($GLOBALS["db"]);
        }
        // Connect back to main to drop the database
        $connString = "host=localhost dbname=dwemer user=dwemer password=dwemer";
        $mainConnection = pg_connect($connString);
        if (!$mainConnection) {
            Logger::error("Failed to connect to main database for dropping test db.");
            return;
        }

        // Drop the database
        $dropResult = pg_query($mainConnection, "DROP DATABASE IF EXISTS ".self::$testDatabaseName." WITH (FORCE)");
        if (!$dropResult) {
            Logger::error("Failed to drop test database: " . pg_last_error($mainConnection));
        }
        $dropResult = pg_query($mainConnection, "DROP DATABASE IF EXISTS ".self::$testDatabaseBkpName." WITH (FORCE)");
        if (!$dropResult) {
            Logger::error("Failed to drop test database: " . pg_last_error($mainConnection));
        }

        pg_close($mainConnection);
    }

    public function tearDownConfFile() {
        $md5name = md5($this->testNPCName);
        @unlink(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR."conf_{$md5name}.php");
        foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . ".conf_{$md5name}*") as $file) {
            @unlink($file);
        }
    }

}