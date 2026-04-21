<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."data_functions.php");

final class CanonicalHoldTestDbAdapter
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

final class CanonicalHoldResolutionTest extends DatabaseTestCase
{
    private string $connString = "host=localhost dbname=testdb user=dwemer password=dwemer";

    public function setUp(): void
    {
        parent::setUp();
        require("conf.php");
        $GLOBALS["db"] = new CanonicalHoldTestDbAdapter($this->connString);
        $this->resetLocationCaches();
    }

    public function tearDown(): void
    {
        if (isset($GLOBALS["db"])) {
            unset($GLOBALS["db"]);
        }

        $this->resetLocationCaches();
        parent::tearDown();
    }

    private function resetLocationCaches(): void
    {
        unset(
            $GLOBALS["CACHE_LAST_KNOWN_LOCATION"],
            $GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"],
            $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"],
            $GLOBALS["CACHE_CANONICAL_HOLD_BY_LOCATION_CANDIDATE"]
        );
    }

    private function insertLocation(string $name, int $formId, string $region, string $hold): void
    {
        $connection = pg_connect($this->connString);
        $this->assertNotFalse($connection);
        pg_query_params(
            $connection,
            "INSERT INTO locations (name, formid, region, hold) VALUES ($1, $2, $3, $4)",
            [$name, $formId, $region, $hold]
        );
        pg_close($connection);
    }

    private function insertLocationEvent(string $contextData, int $localTs = 1, int $gameTs = 100): void
    {
        $connection = pg_connect($this->connString);
        $this->assertNotFalse($connection);
        pg_query_params(
            $connection,
            "INSERT INTO eventlog (ts, gamets, type, data, sess, localts, people, location, party)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)",
            ["0", (string) $gameTs, "location", $contextData, "pending", $localTs, "|The Narrator|", "", "[]"]
        );
        pg_close($connection);
    }

    public function testCanonicalizeHoldNameSupportsLegacyShortForms(): void
    {
        $this->assertSame("Whiterun Hold", canonicalizeHoldName("Whiterun"));
        $this->assertSame("Falkreath Hold", canonicalizeHoldName("Falkreath"));
        $this->assertSame("The Pale", canonicalizeHoldName("the Pale"));
    }

    // Regression note: the plugin can report a town or region-like parent location in the
    // raw Hold field (for example Riverwood or Dawnstar). Keep prompt/rumor hold resolution
    // pinned to the canonical Skyrim hold names instead of echoing that raw parent label.
    public function testCanonicalHoldResolutionMapsRiverwoodToWhiterunHold(): void
    {
        $this->insertLocation("Riverwood", 0x1000, "Whiterun", "Tamriel");
        $this->insertLocation("Riverwood Trader", 0x1001, "Riverwood", "Whiterun");
        $this->insertLocationEvent("(Context new location: Riverwood Trader, Hold: Riverwood)");
        $this->resetLocationCaches();

        $this->assertSame("Riverwood", DataLastKnownLocationHuman(true, false));
        $this->assertSame("Whiterun Hold", DataLastKnownCanonicalHoldHuman(false));
    }

    public function testCanonicalHoldResolutionMapsDawnstarToThePale(): void
    {
        $this->insertLocation("Dawnstar", 0x1002, "the Pale", "Tamriel");
        $this->insertLocationEvent("(Context new location: Dawnstar outdoors, Hold: Dawnstar)");
        $this->resetLocationCaches();

        $this->assertSame("The Pale", DataLastKnownCanonicalHoldHuman(false));
    }

    public function testBuildWorldPromptUsesCanonicalHoldName(): void
    {
        $this->insertLocation("Riverwood", 0x1003, "Whiterun", "Tamriel");
        $this->insertLocation("Riverwood Trader", 0x1004, "Riverwood", "Whiterun");
        $this->insertLocationEvent("(Context new location: Riverwood Trader, Hold: Riverwood)");
        $this->resetLocationCaches();

        $worldPrompt = buildWorldPrompt(100);

        $this->assertStringContainsString("<location>Riverwood Trader</location>", $worldPrompt);
        $this->assertStringContainsString("<hold>Whiterun Hold</hold>", $worldPrompt);
        $this->assertStringNotContainsString("<hold>Riverwood</hold>", $worldPrompt);
    }
}
