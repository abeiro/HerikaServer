<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'oghma_parity.php';

final class ChimOghmaCatalogManager
{
    private const FIELDS_V1 = [
        'topic', 'aliases', 'topic_desc', 'knowledge_class', 'topic_desc_basic',
        'knowledge_class_basic', 'tags', 'category',
    ];
    private const FIELDS_V2 = [
        'topic', 'aliases', 'retrieval_phrases', 'topic_desc', 'knowledge_class', 'topic_desc_basic',
        'knowledge_class_basic', 'tags', 'category',
    ];

    public function __construct(private $db, private string $rootPath)
    {
        if (!$db || !method_exists($db, 'fetchAll') || !method_exists($db, 'execQuery')) {
            throw new InvalidArgumentException('A HerikaServer database connection is required.');
        }
        $this->rootPath = rtrim($rootPath, '/\\');
    }

    public function activePackagePath(): string
    {
        $root = $this->rootPath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'oghma'
            . DIRECTORY_SEPARATOR . 'skyrim-official';
        $pointer = $this->readUtf8File($root . DIRECTORY_SEPARATOR . 'active-catalog-version.txt', 512, 'catalog pointer');
        $version = $this->validateVersion(trim($pointer));
        return $root . DIRECTORY_SEPARATOR . 'catalogs' . DIRECTORY_SEPARATOR . $version;
    }

    /** Validate a complete package before any database write. */
    public function plan(string $packagePath): array
    {
        return $this->loadPackage($packagePath);
    }

    /** Import an immutable package snapshot without changing the active projection. */
    public function import(string $packagePath): array
    {
        $package = $this->loadPackage($packagePath);
        $version = $package['catalog_version'];
        $existing = $this->db->fetchOne(
            'SELECT catalog_version, manifest_sha256, articles_sha256, row_count, state '
            . 'FROM public.oghma_catalogs WHERE catalog_version = ' . $this->literal($version)
        );
        if (is_array($existing) && $existing !== []) {
            if (!hash_equals((string) $existing['manifest_sha256'], $package['manifest_sha256'])
                || !hash_equals((string) $existing['articles_sha256'], $package['articles_sha256'])
                || intval($existing['row_count']) !== count($package['articles'])) {
                throw new RuntimeException('oghma_catalog_version_conflict');
            }
            return ['status' => 'already_imported', 'catalog' => $existing] + $this->packageSummary($package);
        }

        $this->transaction(function () use ($package, $version): void {
            $this->execute(
                'INSERT INTO public.oghma_catalogs '
                . '(catalog_version, contract_version, manifest_sha256, articles_sha256, row_count, state, metadata) VALUES ('
                . implode(', ', [
                    $this->literal($version),
                    $this->literal(CHIM_OGHMA_PARITY_VERSION),
                    $this->literal($package['manifest_sha256']),
                    $this->literal($package['articles_sha256']),
                    (string) count($package['articles']),
                    "'inactive'",
                    $this->literal(json_encode($package['manifest'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '::jsonb',
                ]) . ')'
            );
            foreach (array_chunk($package['articles'], 100) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $columns = [$version];
                    foreach (self::FIELDS_V2 as $field) $columns[] = (string) $row[$field];
                    $columns[] = (string) $row['row_sha256'];
                    $values[] = '(' . implode(', ', array_map(fn(string $value): string => $this->literal($value), $columns)) . ')';
                }
                $this->execute(
                    'INSERT INTO public.oghma_catalog_entries '
                    . '(catalog_version, topic, aliases, retrieval_phrases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic, tags, category, row_sha256) VALUES '
                    . implode(', ', $values)
                );
            }
            $this->event('import', $version, null, ['row_count' => count($package['articles'])]);
        });
        return ['status' => 'imported'] + $this->packageSummary($package);
    }

    /** Atomically project a validated catalog while preserving custom rows and factory hides. */
    public function activate(string $catalogVersion, string $eventType = 'activate', bool $restoreHidden = false): array
    {
        $catalogVersion = $this->validateVersion($catalogVersion);
        if (!in_array($eventType, ['activate', 'rollback'], true)) throw new InvalidArgumentException('invalid_catalog_event');
        $catalog = $this->catalog($catalogVersion);
        if ($catalog === null) throw new RuntimeException('oghma_catalog_missing');
        $entries = $this->catalogEntries($catalogVersion);
        if (count($entries) !== intval($catalog['row_count'])) throw new RuntimeException('oghma_catalog_row_count_drift');

        $result = [];
        $this->transaction(function () use ($catalogVersion, $eventType, $entries, $restoreHidden, &$result): void {
            $this->execute('LOCK TABLE public.oghma_catalogs, public.oghma_catalog_entries, public.oghma_factory_overrides, public.oghma IN SHARE ROW EXCLUSIVE MODE');
            $restoredHidden = 0;
            if ($restoreHidden) {
                $hiddenCountRow = $this->db->fetchOne('SELECT count(*) AS count FROM public.oghma_factory_overrides');
                $restoredHidden = intval($hiddenCountRow['count'] ?? 0);
                $this->execute('DELETE FROM public.oghma_factory_overrides');
            }
            $active = $this->activeCatalog();
            $previous = is_array($active) ? (string) $active['catalog_version'] : null;
            $metadata = json_decode((string) ($this->catalog($catalogVersion)['metadata'] ?? '{}'), true);
            $legacyFactoryChecksums = array_fill_keys(
                is_array($metadata['legacy_factory_row_sha256'] ?? null) ? $metadata['legacy_factory_row_sha256'] : [],
                true
            );

            $legacy = $this->db->fetchAll(
                "SELECT ctid::text AS legacy_ctid, topic, aliases, retrieval_phrases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic, tags, category "
                . "FROM public.oghma WHERE source_type = 'legacy' ORDER BY topic"
            );
            $factoryTopics = [];
            $customTopics = [];
            foreach ($legacy as $row) {
                $topic = (string) ($row['topic'] ?? '');
                $legacyChecksums = $this->rowChecksums($row);
                $legacyChecksum = $legacyChecksums[0];
                $matchesFactory = (isset($entries[$topic])
                    && in_array((string) $entries[$topic]['row_sha256'], $legacyChecksums, true))
                    || array_filter($legacyChecksums, static fn(string $checksum): bool => isset($legacyFactoryChecksums[$checksum])) !== [];
                $source = $matchesFactory ? 'factory' : 'custom';
                $sql = "UPDATE public.oghma SET source_type = '{$source}', updated_at = CURRENT_TIMESTAMP";
                if ($matchesFactory && isset($entries[$topic])) {
                    $sql .= ', source_catalog_version = ' . $this->literal($catalogVersion)
                        . ', source_checksum = ' . $this->literal($entries[$topic]['row_sha256']);
                    $factoryTopics[] = $topic;
                } elseif ($matchesFactory) {
                    $sql .= ', source_catalog_version = NULL, source_checksum = ' . $this->literal($legacyChecksum);
                    $factoryTopics[] = $topic;
                } else {
                    $sql .= ', source_catalog_version = NULL, source_checksum = ' . $this->literal($legacyChecksum);
                    $customTopics[] = $topic;
                }
                $this->execute($sql . ' WHERE ctid = ' . $this->literal((string) $row['legacy_ctid']) . '::tid');
            }
            if ($legacy !== []) {
                $this->event('classify', $catalogVersion, $previous, [
                    'factory' => count($factoryTopics),
                    'custom' => count($customTopics),
                ]);
            }

            $this->execute("DELETE FROM public.oghma WHERE source_type = 'factory'");
            $hiddenRows = $this->db->fetchAll("SELECT topic FROM public.oghma_factory_overrides WHERE action = 'hide'");
            $hidden = array_fill_keys(array_map(static fn(array $row): string => (string) $row['topic'], $hiddenRows), true);
            $customRows = $this->db->fetchAll("SELECT topic FROM public.oghma WHERE source_type = 'custom'");
            $custom = array_fill_keys(array_map(static fn(array $row): string => (string) $row['topic'], $customRows), true);
            $projected = 0;
            $collisions = [];
            $hiddenCount = 0;
            foreach (array_chunk($entries, 100, true) as $chunk) {
                $values = [];
                foreach ($chunk as $topic => $row) {
                    if (isset($hidden[$topic])) {$hiddenCount++; continue;}
                    if (isset($custom[$topic])) {$collisions[] = $topic; continue;}
                    $columns = [];
                    foreach (self::FIELDS_V2 as $field) $columns[] = (string) $row[$field];
                    $columns[] = 'factory';
                    $columns[] = $catalogVersion;
                    $columns[] = (string) $row['row_sha256'];
                    $quoted = array_map(fn(string $value): string => $this->literal($value), $columns);
                    $values[] = '(' . implode(', ', $quoted) . ')';
                    $projected++;
                }
                if ($values !== []) {
                    $this->execute(
                        'INSERT INTO public.oghma '
                        . '(topic, aliases, retrieval_phrases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic, tags, category, source_type, source_catalog_version, source_checksum) VALUES '
                        . implode(', ', $values)
                    );
                }
            }
            $this->execute(
                "UPDATE public.oghma SET native_vector = "
                . "setweight(to_tsvector('simple', coalesce(topic, '')), 'A') "
                . "|| setweight(to_tsvector('simple', coalesce(aliases, '')), 'A') "
                . "|| setweight(to_tsvector(coalesce(topic_desc, '')), 'B') "
                . "|| setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C') "
                . "WHERE source_type = 'factory'"
            );

            $now = 'CURRENT_TIMESTAMP';
            $this->execute("UPDATE public.oghma_catalogs SET state = 'superseded', superseded_at = {$now} WHERE state = 'active' AND catalog_version <> " . $this->literal($catalogVersion));
            $this->execute(
                "UPDATE public.oghma_catalogs SET state = 'active', previous_catalog_version = "
                . ($previous === null || $previous === $catalogVersion ? 'NULL' : $this->literal($previous))
                . ", activated_at = {$now}, superseded_at = NULL WHERE catalog_version = " . $this->literal($catalogVersion)
            );
            $details = [
                'projected' => $projected,
                'custom_collisions' => count($collisions),
                'custom_collision_topics' => array_slice($collisions, 0, 100),
                'hidden' => $hiddenCount,
                'restored_hidden' => $restoredHidden,
                'legacy_factory_classified' => count($factoryTopics),
                'legacy_custom_classified' => count($customTopics),
            ];
            $this->event($eventType, $catalogVersion, $previous, $details);
            $result = ['status' => $eventType === 'rollback' ? 'rolled_back' : 'activated', 'catalog_version' => $catalogVersion,
                'previous_catalog_version' => $previous] + $details;
        });
        return $result;
    }

    public function provisionActivePackage(bool $restoreHidden = false): array
    {
        $package = $this->plan($this->activePackagePath());
        $this->import($this->activePackagePath());
        return $this->activate($package['catalog_version'], 'activate', $restoreHidden);
    }

    public function rollback(?string $catalogVersion = null): array
    {
        $active = $this->activeCatalog();
        if ($active === null) throw new RuntimeException('no_active_oghma_catalog');
        $target = $catalogVersion === null || trim($catalogVersion) === ''
            ? trim((string) ($active['previous_catalog_version'] ?? ''))
            : $this->validateVersion($catalogVersion);
        if ($target === '') throw new RuntimeException('oghma_catalog_rollback_target_missing');
        return $this->activate($target, 'rollback');
    }

    public function status(): array
    {
        $catalogs = $this->db->fetchAll(
            'SELECT catalog_version, contract_version, manifest_sha256, articles_sha256, row_count, state, '
            . 'previous_catalog_version, imported_at, activated_at, superseded_at '
            . 'FROM public.oghma_catalogs ORDER BY imported_at DESC, catalog_version'
        );
        $counts = $this->db->fetchAll('SELECT source_type, count(*) AS count FROM public.oghma GROUP BY source_type ORDER BY source_type');
        return ['contract' => CHIM_OGHMA_PARITY_VERSION, 'catalogs' => $catalogs, 'projection_counts' => $counts];
    }

    public function activeCatalog(): ?array
    {
        $row = $this->db->fetchOne("SELECT * FROM public.oghma_catalogs WHERE state = 'active'");
        return is_array($row) && $row !== [] ? $row : null;
    }

    private function loadPackage(string $packagePath): array
    {
        $manifestRaw = $this->readUtf8File(rtrim($packagePath, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json', 1024 * 1024, 'manifest');
        $manifest = json_decode($manifestRaw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || array_is_list($manifest)
            || ($manifest['contract'] ?? null) !== CHIM_OGHMA_PARITY_VERSION
            || !in_array(intval($manifest['format_version'] ?? 0), [1, 2], true)) {
            throw new InvalidArgumentException('invalid_oghma_manifest');
        }
        $formatVersion = intval($manifest['format_version']);
        $packageFields = $this->fieldsForFormat($formatVersion);
        $version = $this->validateVersion((string) ($manifest['catalog_version'] ?? ''));
        $articlesName = (string) ($manifest['articles_file'] ?? '');
        if ($articlesName !== 'articles.json') throw new InvalidArgumentException('invalid_oghma_articles_file');
        $articlesRaw = $this->readUtf8File(rtrim($packagePath, '/\\') . DIRECTORY_SEPARATOR . $articlesName, 32 * 1024 * 1024, 'articles');
        $articlesSha = hash('sha256', $articlesRaw);
        if (!is_string($manifest['articles_sha256'] ?? null)
            || !hash_equals((string) $manifest['articles_sha256'], $articlesSha)) {
            throw new InvalidArgumentException('oghma_articles_checksum_mismatch');
        }
        $rows = json_decode($articlesRaw, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== intval($manifest['row_count'] ?? -1)) {
            throw new InvalidArgumentException('oghma_catalog_row_count_mismatch');
        }
        $articles = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || array_is_list($row)) throw new InvalidArgumentException('invalid_oghma_article_' . $index);
            $article = [];
            foreach ($packageFields as $field) {
                if (!is_string($row[$field] ?? null) || !mb_check_encoding($row[$field], 'UTF-8')) {
                    throw new InvalidArgumentException('invalid_oghma_article_' . $index . '_' . $field);
                }
                $article[$field] = $field === 'topic' ? trim($row[$field]) : $row[$field];
            }
            if (preg_match('/[|;]/u', $article['aliases']) === 1) {
                throw new InvalidArgumentException('invalid_oghma_alias_separator_' . $article['topic']);
            }
            if ($article['topic'] === '' || mb_strlen($article['topic'], 'UTF-8') > 256 || $article['topic_desc'] === '') {
                throw new InvalidArgumentException('invalid_oghma_article_' . $index);
            }
            $checksum = $this->rowChecksum($article, $packageFields);
            if (!is_string($row['row_sha256'] ?? null) || !hash_equals($checksum, $row['row_sha256'])) {
                throw new InvalidArgumentException('oghma_row_checksum_mismatch_' . $article['topic']);
            }
            if (isset($articles[$article['topic']])) throw new InvalidArgumentException('duplicate_oghma_topic_' . $article['topic']);
            if ($formatVersion === 1) {
                $article['retrieval_phrases'] = '';
            }
            $articles[$article['topic']] = $article + ['row_sha256' => $checksum];
        }
        ksort($articles, SORT_STRING);
        $legacyChecksums = $manifest['legacy_factory_row_sha256'] ?? [];
        if (!is_array($legacyChecksums) || count($legacyChecksums) > 5000) {
            throw new InvalidArgumentException('invalid_oghma_legacy_factory_lineage');
        }
        foreach ($legacyChecksums as $checksum) {
            if (!is_string($checksum) || preg_match('/^[a-f0-9]{64}$/D', $checksum) !== 1) {
                throw new InvalidArgumentException('invalid_oghma_legacy_factory_checksum');
            }
        }
        return [
            'catalog_version' => $version,
            'manifest' => $manifest,
            'manifest_sha256' => hash('sha256', $manifestRaw),
            'articles_sha256' => $articlesSha,
            'articles' => $articles,
        ];
    }

    private function catalog(string $version): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM public.oghma_catalogs WHERE catalog_version = ' . $this->literal($version));
        return is_array($row) && $row !== [] ? $row : null;
    }

    private function catalogEntries(string $version): array
    {
        $rows = $this->db->fetchAll(
            'SELECT topic, aliases, retrieval_phrases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic, tags, category, row_sha256 '
            . 'FROM public.oghma_catalog_entries WHERE catalog_version = ' . $this->literal($version) . ' ORDER BY topic'
        );
        $entries = [];
        foreach ($rows as $row) $entries[(string) $row['topic']] = $row;
        return $entries;
    }

    private function fieldsForFormat(int $formatVersion): array
    {
        return $formatVersion === 2 ? self::FIELDS_V2 : self::FIELDS_V1;
    }

    private function rowChecksum(array $row, array $fields = self::FIELDS_V2): string
    {
        $ordered = [];
        foreach ($fields as $field) $ordered[$field] = (string) ($row[$field] ?? '');
        return hash('sha256', json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Compare both package generations while classifying pre-catalog legacy rows. */
    private function rowChecksums(array $row): array
    {
        return array_values(array_unique([
            $this->rowChecksum($row, self::FIELDS_V2),
            $this->rowChecksum($row, self::FIELDS_V1),
        ]));
    }

    private function event(string $type, string $version, ?string $previous, array $details): void
    {
        $this->execute(
            'INSERT INTO public.oghma_catalog_events (event_type, catalog_version, previous_catalog_version, details) VALUES ('
            . $this->literal($type) . ', ' . $this->literal($version) . ', '
            . ($previous === null ? 'NULL' : $this->literal($previous)) . ', '
            . $this->literal(json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '::jsonb)'
        );
    }

    private function transaction(callable $callback): void
    {
        $this->execute('BEGIN');
        try {
            $callback();
            $this->execute('COMMIT');
        } catch (Throwable $error) {
            try {$this->db->execQuery('ROLLBACK');} catch (Throwable) {}
            throw $error;
        }
    }

    private function execute(string $query): void
    {
        if ($this->db->execQuery($query) === false) {
            throw new RuntimeException('oghma_catalog_database_write_failed');
        }
    }

    private function literal(string $value): string
    {
        if (method_exists($this->db, 'escapeLiteral')) return $this->db->escapeLiteral($value);
        if (method_exists($this->db, 'escape')) return "'" . $this->db->escape($value) . "'";
        throw new RuntimeException('Database connection cannot escape literals.');
    }

    private function validateVersion(string $version): string
    {
        $version = trim($version);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $version) !== 1) {
            throw new InvalidArgumentException('invalid_oghma_catalog_version');
        }
        return $version;
    }

    private function readUtf8File(string $path, int $maximumBytes, string $label): string
    {
        if (!is_file($path) || !is_readable($path)) throw new InvalidArgumentException($label . ' file is unavailable');
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > $maximumBytes) throw new InvalidArgumentException($label . ' file size is invalid');
        $value = file_get_contents($path);
        if ($value === false || !mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException($label . ' must be valid UTF-8');
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    private function packageSummary(array $package): array
    {
        return [
            'catalog_version' => $package['catalog_version'],
            'row_count' => count($package['articles']),
            'articles_sha256' => $package['articles_sha256'],
            'manifest_sha256' => $package['manifest_sha256'],
        ];
    }
}
