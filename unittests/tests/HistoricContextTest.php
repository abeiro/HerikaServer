<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."data_functions.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

final class HistoricContextTestDbAdapter
{
    private string $connString;

    public function __construct(string $connString)
    {
        $this->connString = $connString;
    }

    private function connect()
    {
        $connection = pg_connect($this->connString);
        if ($connection === false) {
            throw new RuntimeException("Failed to connect to test database.");
        }

        return $connection;
    }

    public function fetchAll(string $query): array
    {
        $connection = $this->connect();
        $result = pg_query($connection, $query);
        if ($result === false) {
            pg_close($connection);
            return [];
        }

        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }

        pg_close($connection);
        return $rows;
    }

    public function escape(string $value): string
    {
        $connection = $this->connect();
        $escaped = pg_escape_string($connection, $value);
        pg_close($connection);
        return $escaped;
    }
}

final class HistoricContextTest extends DatabaseTestCase
{
    private string $connString = "host=localhost dbname=testdb user=dwemer password=dwemer";

    public function setUp(): void
    {
        parent::setUp();
        require("conf.php");
        $GLOBALS["db"] = new HistoricContextTestDbAdapter($this->connString);
        $GLOBALS["PLAYER_NAME"] = "Prisoner";
        $GLOBALS["HERIKA_NAME"] = "Lydia";
        $GLOBALS["gameRequest"] = ["chat", "200", "200", ""];
    }

    public function tearDown(): void
    {
        unset(
            $GLOBALS["db"],
            $GLOBALS["gameRequest"],
            $GLOBALS["PLAYER_NAME"],
            $GLOBALS["HERIKA_NAME"]
        );

        parent::tearDown();
    }

    private function insertEvent(
        string $type,
        string $data,
        string $people,
        int $ts,
        int $gameTs,
        int $localTs
    ): void {
        $connection = pg_connect($this->connString);
        $this->assertNotFalse($connection);
        pg_query_params(
            $connection,
            "INSERT INTO eventlog (ts, gamets, type, data, sess, localts, people, location, party)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)",
            [
                (string)$ts,
                (string)$gameTs,
                $type,
                $data,
                "pending",
                $localTs,
                $people,
                "",
                "[]",
            ]
        );
        pg_close($connection);
    }

    public function testBuildHistoricContextIncludesRestrainedAudienceRows(): void
    {
        $this->insertEvent(
            "inputtext",
            "Prisoner: Keep your voice down.",
            "|Lydia (restrained)|Prisoner|",
            100,
            100,
            100
        );

        $context = buildHistoricContext("Lydia", -5);
        $contents = array_map(static function (array $row): string {
            return (string)($row["content"] ?? "");
        }, $context);

        $this->assertContains("Prisoner: Keep your voice down.", $contents);
    }

    public function testBuildHistoricContextIncludesNarratorRowsForSharedAudience(): void
    {
        $this->insertEvent(
            "chat",
            "The Narrator: A cold wind sweeps through the inn.",
            "|Lydia|Prisoner|The Narrator|",
            100,
            100,
            100
        );

        $context = buildHistoricContext("Lydia", -5);
        $contents = array_map(static function (array $row): string {
            return (string)($row["content"] ?? "");
        }, $context);

        $this->assertContains("The Narrator: A cold wind sweeps through the inn.", $contents);
    }

    public function testBuildHistoricContextStillExcludesNonNarratorRowsForFarAwayAudience(): void
    {
        $this->insertEvent(
            "chat",
            "Belethor: Everything's for sale, my friend.",
            "|Lydia (far away)|Prisoner|Belethor|",
            100,
            100,
            100
        );

        $context = buildHistoricContext("Lydia", -5);
        $contents = array_map(static function (array $row): string {
            return (string)($row["content"] ?? "");
        }, $context);

        $this->assertNotContains("Belethor: Everything's for sale, my friend.", $contents);
    }

    public function testNormalizeActorNameForComparisonStripsRestrainedSuffix(): void
    {
        $this->assertSame("lydia", normalizeActorNameForComparison("Lydia (restrained)"));
    }
}
