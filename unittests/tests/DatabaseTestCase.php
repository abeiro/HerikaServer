<?php

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected static string $testDatabaseName = "testdb";
    protected string $testNPCName = "Unit Test";

    public function setUp(): void
    {
        $this->setUpDB();
        $this->setUpDefaultMinimeMocks();
        $this->setUpDefaultConnectorMocks();
        $this->setUpConfFile();
    }

    public function tearDown(): void
    {
        $this->tearDownDB();
        $this->tearDownConfFile();
    }

    public function setUpDB(): void
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

        // Create the test database
        $createResult = pg_query($mainConnection, "CREATE DATABASE ".self::$testDatabaseName." TEMPLATE template0");
        if (!$createResult) {
            $this->fail("Failed to create test database: " . pg_last_error($mainConnection));
        }

        pg_close($mainConnection);

        // Connect to the new test database
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."phpunit.class.php");
        $testConnection = new sql();

        // Run migrations/seeders
        // Path to SQL file to import
        $path = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..";
        $sqlFile = $path.DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."database_default.sql";

        // Command to import SQL file using psql
        $psqlCommand = "PGPASSWORD=dwemer psql -h localhost -p 5432 -U dwemer -d ".self::$testDatabaseName." -f $sqlFile";

        // Execute psql command
        $output = [];
        $returnVar = 0;
        exec($psqlCommand, $output, $returnVar);


        // if minAI is installed then create its database tables as well, to avoid errors
        if (file_exists($path.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR."minai_plugin")) {
            require_once($path.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR."minai_plugin".DIRECTORY_SEPARATOR."importDataToDB.php");
            require_once($path.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR."minai_plugin".DIRECTORY_SEPARATOR."customintegrations.php");
            $GLOBALS["db"] = $testConnection;

            CreateThreadsTableIfNotExists();
            CreateActionsTableIfNotExists();
            CreateContextTableIfNotExists();
            importXPersonalities();
            importScenesDescriptions();
            unset($GLOBALS["db"]);
        }

        $testConnection->close();
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
            return '{"input_text": "'.$text.'", "generated_tags": "", "elapsed_time": "0.05 seconds"}';
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

    public function tearDownDB(): void
    {
        if (isset($GLOBALS["db"])) {
            $GLOBALS["db"]->close();
            unset($GLOBALS["db"]);
        }
        // Connect back to main to drop the database
        $connString = "host=localhost dbname=dwemer user=dwemer password=dwemer";
        $mainConnection = pg_connect($connString);
        if (!$mainConnection) {
            error_log("Failed to connect to main database for dropping test db.");
            return;
        }

        // Drop the database
        $dropResult = pg_query($mainConnection, "DROP DATABASE IF EXISTS ".self::$testDatabaseName." WITH (FORCE)");
        if (!$dropResult) {
            error_log("Failed to drop test database: " . pg_last_error($mainConnection));
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