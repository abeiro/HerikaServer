<?php

declare(strict_types=1);

namespace HerikaServer\Database;

use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public const BASELINE_VERSION = 202608110000;
    public const LEDGER = 'chim_meta.schema_migrations';

    private const LOCK_ID = 4_843_749_526_024_081;

    /** @var resource|\PgSql\Connection */
    private $connection;
    private string $root;
    private ?string $applicationCommit;
    private bool $ownsConnection;

    /**
     * The runner owns migration ordering, locking, transactions, and ledger writes.
     *
     * @param resource|\PgSql\Connection $connection
     */
    public function __construct($connection, string $root, ?string $applicationCommit = null, bool $ownsConnection = false)
    {
        if (!$connection) {
            throw new RuntimeException('A PostgreSQL connection is required.');
        }

        $this->connection = $connection;
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->applicationCommit = $applicationCommit;
        $this->ownsConnection = $ownsConnection;
    }

    public static function connect(string $root, ?string $dsn = null, ?string $applicationCommit = null): self
    {
        if (!function_exists('pg_connect')) {
            throw new RuntimeException('The PostgreSQL PHP extension is required.');
        }

        $dsn = $dsn ?: (getenv('HERIKA_DATABASE_DSN') ?: 'host=localhost port=5432 dbname=dwemer user=dwemer password=dwemer connect_timeout=15');
        $connection = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        if (!$connection) {
            throw new RuntimeException('Could not connect to PostgreSQL for database migrations.');
        }

        return new self($connection, $root, $applicationCommit, true);
    }

    public function __destruct()
    {
        if ($this->ownsConnection && $this->connection) {
            try {
                @pg_close($this->connection);
            } catch (Throwable) {
                // Destructors must not mask the command or request result.
            }
        }
    }

    public static function latestVersion(string $root): int
    {
        return max(array_keys(self::sourceManifest($root)));
    }

    /** @return array<int,array{version:int,name:string,type:string,path:string,checksum:string}> */
    public static function sourceManifest(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $contractPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baseline'
            . DIRECTORY_SEPARATOR . self::BASELINE_VERSION . '_contract.json';
        $contract = @file_get_contents($contractPath);
        if ($contract === false || trim($contract) === '') {
            throw new RuntimeException("Baseline contract is missing or empty: {$contractPath}");
        }

        $migrations = [
            self::BASELINE_VERSION => [
                'version' => self::BASELINE_VERSION,
                'name' => 'legacy_baseline',
                'type' => 'baseline',
                'path' => $contractPath,
                'checksum' => self::sourceChecksum($contract),
            ],
        ];
        $directory = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $paths = glob($directory . DIRECTORY_SEPARATOR . '*.{sql,php}', GLOB_BRACE) ?: [];
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            $filename = basename($path);
            if (preg_match('/^(\d{12})_([a-z0-9_]+)\.(sql|php)$/D', $filename, $match) !== 1) {
                throw new RuntimeException("Invalid migration filename: {$filename}");
            }
            $version = (int) $match[1];
            if ($version <= self::BASELINE_VERSION) {
                throw new RuntimeException("Migration {$filename} must be newer than the legacy baseline.");
            }
            if (isset($migrations[$version])) {
                throw new RuntimeException("Duplicate migration version: {$version}");
            }
            $contents = @file_get_contents($path);
            if ($contents === false || trim($contents) === '') {
                throw new RuntimeException("Migration is missing or empty: {$filename}");
            }
            if ($match[3] === 'sql' && self::containsTransactionControl($contents)) {
                throw new RuntimeException("Migration {$filename} controls transactions; the runner must own the transaction.");
            }
            $migrations[$version] = [
                'version' => $version,
                'name' => $match[2],
                'type' => $match[3],
                'path' => $path,
                'checksum' => self::sourceChecksum($contents),
            ];
        }
        ksort($migrations, SORT_NUMERIC);
        return $migrations;
    }

    /** @return array{ready:bool,ledger_exists:bool,baseline_problems:list<string>,schema_problems:list<string>,applied:list<array<string,mixed>>,pending:list<array<string,mixed>>} */
    public function status(): array
    {
        $migrations = $this->discover();
        $ledgerExists = $this->ledgerExists();
        $applied = $ledgerExists ? $this->applied() : [];
        $baselineProblems = isset($applied[self::BASELINE_VERSION]) ? [] : $this->validateBaseline();

        if ($ledgerExists) {
            $this->assertNoDrift($migrations, $applied);
        }

        $pending = [];
        foreach ($migrations as $version => $migration) {
            if (!isset($applied[$version])) {
                $pending[] = $migration;
            }
        }
        $schemaProblems = $baselineProblems === [] && $pending === []
            ? $this->validateContract($this->schemaContractPath(), false)
            : [];

        return [
            'ready' => $baselineProblems === [] && $pending === [] && $schemaProblems === [],
            'ledger_exists' => $ledgerExists,
            'baseline_problems' => $baselineProblems,
            'schema_problems' => $schemaProblems,
            'applied' => array_values($applied),
            'pending' => $pending,
        ];
    }

    /** @return list<int> */
    public function migrate(): array
    {
        return $this->locked(function (): array {
            $migrations = $this->discover();
            $ledgerExists = $this->ledgerExists();
            $applied = $ledgerExists ? $this->applied() : [];

            if (!isset($applied[self::BASELINE_VERSION])) {
                $problems = $this->validateBaseline();
                if ($problems !== []) {
                    throw new RuntimeException(
                        "Legacy baseline reconciliation failed:\n- " . implode("\n- ", $problems)
                        . "\nNo schema migration was recorded. Run the explicit legacy bridge on a backup or repair the reported schema differences."
                    );
                }
                $this->ensureLedger();
                $this->recordBaseline($migrations[self::BASELINE_VERSION]);
                $applied = $this->applied();
            }

            $this->assertNoDrift($migrations, $applied);
            $ran = [];
            foreach ($migrations as $version => $migration) {
                if (isset($applied[$version])) {
                    continue;
                }
                $this->apply($migration);
                $ran[] = $version;
            }

            $schemaProblems = $this->validateContract($this->schemaContractPath(), false);
            if ($schemaProblems !== []) {
                throw new RuntimeException("Post-migration schema verification failed:\n- " . implode("\n- ", $schemaProblems));
            }

            return $ran;
        });
    }

    public function repairLegacyBaseline(): void
    {
        $this->locked(function (): void {
            if ($this->ledgerExists() && isset($this->applied()[self::BASELINE_VERSION])) {
                return;
            }

            $path = $this->root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'legacy-baseline-repair.sql';
            $sql = @file_get_contents($path);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException("Legacy baseline repair is missing or empty: {$path}");
            }
            if (self::containsTransactionControl($sql)) {
                throw new RuntimeException('Legacy baseline repair controls transactions; the runner must own the transaction.');
            }

            $this->transaction(function () use ($sql): void {
                $this->execute($sql);
                $problems = $this->validateBaseline();
                if ($problems !== []) {
                    throw new RuntimeException("Legacy baseline remains incompatible after repair:\n- " . implode("\n- ", $problems));
                }
            });
        });
    }

    /** @return list<string> */
    public function verify(): array
    {
        $status = $this->status();
        $problems = array_merge($status['baseline_problems'], $status['schema_problems']);
        foreach ($status['pending'] as $migration) {
            $problems[] = "Pending migration {$migration['version']} {$migration['name']}";
        }
        return $problems;
    }

    /** @return array<int,array{version:int,name:string,type:string,path:string,checksum:string,applied_at?:string,execution_time_ms?:string,application_commit?:?string}> */
    private function discover(): array
    {
        return self::sourceManifest($this->root);
    }

    private static function containsTransactionControl(string $sql): bool
    {
        $withoutDollarBodies = preg_replace('/\$([A-Za-z_][A-Za-z0-9_]*)\$.*?\$\1\$/s', '', $sql);
        if ($withoutDollarBodies !== null) {
            $withoutDollarBodies = preg_replace('/\$\$.*?\$\$/s', '', $withoutDollarBodies);
        }
        if ($withoutDollarBodies === null) {
            throw new RuntimeException('Could not inspect migration transaction control.');
        }
        return preg_match('/(^|;)\s*(BEGIN|START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i', $withoutDollarBodies) === 1;
    }

    /** Keep migration identities stable when Git checks files out with platform-native line endings. */
    private static function sourceChecksum(string $contents): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
    }

    private function contractPath(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baseline'
            . DIRECTORY_SEPARATOR . self::BASELINE_VERSION . '_contract.json';
    }

    private function schemaContractPath(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema-contract.json';
    }

    /** @return list<string> */
    private function validateBaseline(): array
    {
        return $this->validateContract($this->contractPath(), true);
    }

    /** @return list<string> */
    private function validateContract(string $path, bool $requireBaselineVersion): array
    {
        $raw = @file_get_contents($path);
        $contract = $raw === false ? null : json_decode($raw, true);
        if (!is_array($contract)) {
            throw new RuntimeException("Schema contract is invalid: {$path}");
        }
        if ($requireBaselineVersion && (int) ($contract['baseline_version'] ?? 0) !== self::BASELINE_VERSION) {
            throw new RuntimeException("Baseline contract version is invalid: {$path}");
        }
        if (!$requireBaselineVersion && (int) ($contract['schema_version'] ?? 0) !== self::latestVersion($this->root)) {
            throw new RuntimeException("Current schema contract version does not match the latest migration: {$path}");
        }

        $problems = [];
        $extensions = $this->columnValues('SELECT extname FROM pg_extension', 'extname');
        foreach (($contract['extensions'] ?? []) as $extension) {
            if (!isset($extensions[$extension])) {
                $problems[] = "Missing extension {$extension}";
            }
        }

        $relations = $this->rows(
            "SELECT table_schema, table_name, table_type FROM information_schema.tables "
            . "WHERE table_schema IN ('public','chim_meta')"
        );
        $relationMap = [];
        foreach ($relations as $row) {
            $relationMap[$row['table_schema'] . '.' . $row['table_name']] = $row['table_type'];
        }

        $columns = $this->rows(
            "SELECT table_schema, table_name, column_name, udt_schema, udt_name, is_nullable "
            . "FROM information_schema.columns WHERE table_schema IN ('public','chim_meta')"
        );
        $columnMap = [];
        foreach ($columns as $row) {
            $columnMap[$row['table_schema'] . '.' . $row['table_name']][$row['column_name']] = [
                'udt_schema' => $row['udt_schema'],
                'udt_name' => $row['udt_name'],
                'nullable' => $row['is_nullable'] === 'YES',
            ];
        }

        foreach (($contract['tables'] ?? []) as $relation => $definition) {
            if (($relationMap[$relation] ?? null) !== 'BASE TABLE') {
                $problems[] = "Missing table {$relation}";
                continue;
            }
            foreach (($definition['columns'] ?? []) as $column => $expected) {
                $actual = $columnMap[$relation][$column] ?? null;
                if ($actual === null) {
                    $problems[] = "Missing column {$relation}.{$column}";
                    continue;
                }
                foreach (['udt_schema', 'udt_name', 'nullable'] as $property) {
                    if (($actual[$property] ?? null) !== ($expected[$property] ?? null)) {
                        $actualValue = is_bool($actual[$property] ?? null) ? (($actual[$property] ?? false) ? 'true' : 'false') : strval($actual[$property] ?? 'null');
                        $expectedValue = is_bool($expected[$property] ?? null) ? (($expected[$property] ?? false) ? 'true' : 'false') : strval($expected[$property] ?? 'null');
                        $problems[] = "Column {$relation}.{$column} {$property} is {$actualValue}; expected {$expectedValue}";
                    }
                }
            }
        }

        $viewDefinitions = [];
        foreach ($this->rows("SELECT schemaname, viewname, definition FROM pg_views WHERE schemaname IN ('public','chim_meta')") as $row) {
            $viewDefinitions[$row['schemaname'] . '.' . $row['viewname']] = trim($row['definition']);
        }
        foreach (($contract['views'] ?? []) as $view => $expectedDefinition) {
            if (($relationMap[$view] ?? null) !== 'VIEW') {
                $problems[] = "Missing view {$view}";
            } elseif (($viewDefinitions[$view] ?? null) !== trim(strval($expectedDefinition))) {
                $problems[] = "View definition drift detected for {$view}";
            }
        }

        $constraintRows = $this->rows(
            "SELECT n.nspname AS schema_name, c.relname AS table_name, con.conname "
            . "FROM pg_constraint con JOIN pg_class c ON c.oid=con.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace "
            . "WHERE n.nspname IN ('public','chim_meta')"
        );
        $constraints = [];
        foreach ($constraintRows as $row) {
            $constraints[$row['schema_name'] . '.' . $row['table_name'] . '.' . $row['conname']] = true;
        }
        foreach (($contract['constraints'] ?? []) as $constraint) {
            if (!isset($constraints[$constraint])) {
                $problems[] = "Missing constraint {$constraint}";
            }
        }

        $indexRows = $this->rows("SELECT schemaname, tablename, indexname FROM pg_indexes WHERE schemaname IN ('public','chim_meta')");
        $indexes = [];
        foreach ($indexRows as $row) {
            $indexes[$row['schemaname'] . '.' . $row['tablename'] . '.' . $row['indexname']] = true;
        }
        foreach (($contract['indexes'] ?? []) as $index) {
            if (!isset($indexes[$index])) {
                $problems[] = "Missing index {$index}";
            }
        }

        return $problems;
    }

    private function ledgerExists(): bool
    {
        $result = $this->value("SELECT to_regclass('" . self::LEDGER . "')");
        return $result === self::LEDGER;
    }

    private function ensureLedger(): void
    {
        $this->execute('CREATE SCHEMA IF NOT EXISTS chim_meta');
        $this->execute(
            'CREATE TABLE IF NOT EXISTS ' . self::LEDGER . ' ('
            . 'version bigint PRIMARY KEY, '
            . 'name text NOT NULL, '
            . 'checksum char(64) NOT NULL, '
            . 'applied_at timestamptz NOT NULL DEFAULT clock_timestamp(), '
            . 'execution_time_ms numeric(14,3) NOT NULL, '
            . 'application_commit text)'
        );
    }

    /** @return array<int,array{version:int,name:string,type:string,path:string,checksum:string,applied_at:string,execution_time_ms:string,application_commit:?string}> */
    private function applied(): array
    {
        $rows = $this->rows(
            'SELECT version, name, checksum, applied_at, execution_time_ms, application_commit '
            . 'FROM ' . self::LEDGER . ' ORDER BY version'
        );
        $applied = [];
        foreach ($rows as $row) {
            $version = (int) $row['version'];
            $applied[$version] = [
                'version' => $version,
                'name' => $row['name'],
                'type' => $version === self::BASELINE_VERSION ? 'baseline' : 'applied',
                'path' => '',
                'checksum' => rtrim($row['checksum']),
                'applied_at' => $row['applied_at'],
                'execution_time_ms' => $row['execution_time_ms'],
                'application_commit' => $row['application_commit'],
            ];
        }
        return $applied;
    }

    private function assertNoDrift(array $migrations, array $applied): void
    {
        $encounteredPending = false;
        foreach ($migrations as $version => $migration) {
            if (!isset($applied[$version])) {
                $encounteredPending = true;
                continue;
            }
            if ($encounteredPending) {
                throw new RuntimeException("Applied migration history has a gap before {$version}.");
            }
            if ($applied[$version]['name'] !== $migration['name']) {
                throw new RuntimeException("Migration name drift detected at {$version}.");
            }
            if (!hash_equals($migration['checksum'], $applied[$version]['checksum'])) {
                throw new RuntimeException("Migration checksum drift detected at {$version}.");
            }
        }
        foreach ($applied as $version => $migration) {
            if (!isset($migrations[$version])) {
                throw new RuntimeException("Applied migration {$version} is absent from source control.");
            }
        }
    }

    private function recordBaseline(array $baseline): void
    {
        $this->transaction(function () use ($baseline): void {
            $this->insertLedger($baseline, 0.0);
        });
    }

    private function apply(array $migration): void
    {
        $started = microtime(true);
        $this->transaction(function () use ($migration, $started): void {
            if ($migration['type'] === 'sql') {
                $sql = file_get_contents($migration['path']);
                if ($sql === false) {
                    throw new RuntimeException("Could not read migration {$migration['version']}.");
                }
                $this->execute($sql);
            } elseif ($migration['type'] === 'php') {
                $callable = require $migration['path'];
                if (!is_callable($callable)) {
                    throw new RuntimeException("PHP migration {$migration['version']} must return a callable.");
                }
                $callable($this->connection);
            } else {
                throw new RuntimeException("Unsupported migration type {$migration['type']}.");
            }
            $this->insertLedger($migration, (microtime(true) - $started) * 1000);
        });
    }

    private function insertLedger(array $migration, float $executionTimeMs): void
    {
        $statement = @pg_query_params(
            $this->connection,
            'INSERT INTO ' . self::LEDGER . ' (version, name, checksum, execution_time_ms, application_commit) VALUES ($1,$2,$3,$4,$5)',
            [
                (string) $migration['version'],
                $migration['name'],
                $migration['checksum'],
                number_format($executionTimeMs, 3, '.', ''),
                $this->applicationCommit,
            ]
        );
        if (!$statement) {
            throw new RuntimeException('Could not record migration: ' . pg_last_error($this->connection));
        }
    }

    private function transaction(callable $callback): void
    {
        $this->execute('BEGIN');
        try {
            $this->execute('SET LOCAL search_path TO public, pg_temp');
            $callback();
            $this->execute('COMMIT');
        } catch (Throwable $error) {
            @pg_query($this->connection, 'ROLLBACK');
            throw $error;
        }
    }

    private function locked(callable $callback): mixed
    {
        $result = @pg_query_params($this->connection, 'SELECT pg_advisory_lock($1)', [(string) self::LOCK_ID]);
        if (!$result) {
            throw new RuntimeException('Could not acquire migration lock: ' . pg_last_error($this->connection));
        }
        try {
            return $callback();
        } finally {
            @pg_query_params($this->connection, 'SELECT pg_advisory_unlock($1)', [(string) self::LOCK_ID]);
        }
    }

    private function execute(string $sql): void
    {
        if (!@pg_query($this->connection, $sql)) {
            throw new RuntimeException(pg_last_error($this->connection));
        }
    }

    /** @return list<array<string,string|null>> */
    private function rows(string $sql): array
    {
        $result = @pg_query($this->connection, $sql);
        if (!$result) {
            throw new RuntimeException(pg_last_error($this->connection));
        }
        return pg_fetch_all($result) ?: [];
    }

    private function value(string $sql): ?string
    {
        $result = @pg_query($this->connection, $sql);
        if (!$result) {
            throw new RuntimeException(pg_last_error($this->connection));
        }
        $value = pg_fetch_result($result, 0, 0);
        return $value === false || $value === null ? null : (string) $value;
    }

    /** @return array<string,true> */
    private function columnValues(string $sql, string $column): array
    {
        $values = [];
        foreach ($this->rows($sql) as $row) {
            if (isset($row[$column])) {
                $values[$row[$column]] = true;
            }
        }
        return $values;
    }
}
