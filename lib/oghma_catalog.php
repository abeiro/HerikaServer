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

    /** Make the validated checked-in dataset the complete factory projection in one transaction. */
    public function synchronize(string $packagePath, bool $restoreHidden = false, bool $upgradeSchema = false): array
    {
        $package = $this->loadPackage($packagePath);
        $catalogVersion = $package['catalog_version'];
        $entries = $package['articles'];

        $result = [];
        $this->transaction(function () use ($package, $catalogVersion, $entries, $restoreHidden, $upgradeSchema, &$result): void {
            if ($upgradeSchema) {
                $schema = $this->readUtf8File(
                    __DIR__ . '/core/database_schema/oghma_catalog.sql', 64 * 1024, 'Oghma schema'
                );
                $this->execute($schema);
            }
            $this->execute('LOCK TABLE public.oghma_catalogs, public.oghma_catalog_entries, public.oghma_factory_overrides, public.oghma IN SHARE ROW EXCLUSIVE MODE');
            $restoredHidden = 0;
            if ($restoreHidden) {
                $hiddenCountRow = $this->db->fetchOne('SELECT count(*) AS count FROM public.oghma_factory_overrides');
                $restoredHidden = intval($hiddenCountRow['count'] ?? 0);
                $this->execute('DELETE FROM public.oghma_factory_overrides');
            }
            $metadata = $package['manifest'];
            $legacyFactoryChecksums = array_fill_keys(
                is_array($metadata['legacy_factory_row_sha256'] ?? null) ? $metadata['legacy_factory_row_sha256'] : [],
                true
            );

            $this->execute("DELETE FROM public.oghma WHERE source_type = 'factory'");
            $this->execute("UPDATE public.oghma SET source_catalog_version = NULL WHERE source_type <> 'factory' AND source_catalog_version IS NOT NULL");
            $this->execute('DELETE FROM public.oghma_catalog_events');
            $this->execute('DELETE FROM public.oghma_catalogs');
            $this->execute(
                'INSERT INTO public.oghma_catalogs '
                . '(catalog_version, contract_version, manifest_sha256, articles_sha256, row_count, state, previous_catalog_version, imported_at, activated_at, superseded_at, metadata) VALUES ('
                . implode(', ', [
                    $this->literal($catalogVersion),
                    $this->literal(CHIM_OGHMA_PARITY_VERSION),
                    $this->literal($package['manifest_sha256']),
                    $this->literal($package['articles_sha256']),
                    (string) count($entries),
                    "'active'",
                    'NULL',
                    'CURRENT_TIMESTAMP',
                    'CURRENT_TIMESTAMP',
                    'NULL',
                    $this->literal(json_encode($package['manifest'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '::jsonb',
                ]) . ')'
            );
            foreach (array_chunk($entries, 100) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $columns = [$catalogVersion];
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

            $details = [
                'projected' => $projected,
                'custom_collisions' => count($collisions),
                'custom_collision_topics' => array_slice($collisions, 0, 100),
                'hidden' => $hiddenCount,
                'restored_hidden' => $restoredHidden,
                'legacy_factory_classified' => count($factoryTopics),
                'legacy_custom_classified' => count($customTopics),
            ];
            $result = ['status' => 'synchronized', 'catalog_version' => $catalogVersion] + $details;
        });
        return $result;
    }

    public function provisionActivePackage(bool $restoreHidden = false, bool $upgradeSchema = false): array
    {
        return $this->synchronize($this->activePackagePath(), $restoreHidden, $upgradeSchema);
    }

    public function status(): array
    {
        $current = $this->activeCatalog();
        $counts = $this->db->fetchAll('SELECT source_type, count(*) AS count FROM public.oghma GROUP BY source_type ORDER BY source_type');
        return ['contract' => CHIM_OGHMA_PARITY_VERSION, 'current_dataset' => $current, 'projection_counts' => $counts];
    }

    public function activeCatalog(): ?array
    {
        $row = $this->db->fetchOne("SELECT * FROM public.oghma_catalogs WHERE state = 'active'");
        return is_array($row) && $row !== [] ? $row : null;
    }

    /** Delete one custom article and immediately restore its active factory source, when present. */
    public function deleteCustomOverride(string $topic): array
    {
        $topic = trim($topic);
        if ($topic === '') throw new InvalidArgumentException('invalid_oghma_topic');

        $result = [];
        $this->transaction(function () use ($topic, &$result): void {
            $this->lockLifecycleTables();
            $row = $this->db->fetchOne(
                "SELECT source_type FROM public.oghma WHERE topic = " . $this->literal($topic) . ' FOR UPDATE'
            );
            if (($row['source_type'] ?? null) !== 'custom') {
                throw new InvalidArgumentException('oghma_custom_override_not_found');
            }

            $deleted = $this->executeAffected(
                "DELETE FROM public.oghma WHERE topic = " . $this->literal($topic) . " AND source_type = 'custom'"
            );
            $restored = $this->restoreFactoryProjection($topic);
            $result = ['deleted' => $deleted, 'factory_restored' => $restored];
        });
        return $result;
    }

    /** Delete all custom articles and restore every active factory article they overrode. */
    public function deleteAllCustomOverrides(): array
    {
        $result = [];
        $this->transaction(function () use (&$result): void {
            $this->lockLifecycleTables();
            $deleted = $this->executeAffected("DELETE FROM public.oghma WHERE source_type = 'custom'");
            $restored = $this->restoreFactoryProjection();
            $result = ['deleted' => $deleted, 'factory_restored' => $restored];
        });
        return $result;
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

    /** Serialize factory/custom lifecycle changes in the same order as catalog synchronization. */
    private function lockLifecycleTables(): void
    {
        $this->execute('LOCK TABLE public.oghma_catalogs, public.oghma_catalog_entries, public.oghma_factory_overrides, public.oghma IN SHARE ROW EXCLUSIVE MODE');
    }

    /** Rebuild missing effective rows from the active factory source without disturbing custom rows. */
    private function restoreFactoryProjection(?string $topic = null): int
    {
        $topicFilter = $topic === null ? '' : ' AND entry.topic = ' . $this->literal($topic);
        $restored = $this->executeAffected(
            'INSERT INTO public.oghma '
            . '(topic, aliases, retrieval_phrases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic, tags, category, '
            . 'source_type, source_catalog_version, source_checksum, updated_at) '
            . 'SELECT entry.topic, entry.aliases, entry.retrieval_phrases, entry.topic_desc, entry.knowledge_class, '
            . 'entry.topic_desc_basic, entry.knowledge_class_basic, entry.tags, entry.category, '
            . "'factory', entry.catalog_version, entry.row_sha256, CURRENT_TIMESTAMP "
            . 'FROM public.oghma_catalog_entries entry '
            . "JOIN public.oghma_catalogs catalog ON catalog.catalog_version = entry.catalog_version AND catalog.state = 'active' "
            . 'WHERE NOT EXISTS (SELECT 1 FROM public.oghma current_row WHERE current_row.topic = entry.topic) '
            . "AND NOT EXISTS (SELECT 1 FROM public.oghma_factory_overrides hidden WHERE hidden.topic = entry.topic AND hidden.action = 'hide')"
            . $topicFilter
        );
        if ($restored > 0) {
            $topicVectorFilter = $topic === null ? '' : ' AND topic = ' . $this->literal($topic);
            $this->execute(
                "UPDATE public.oghma SET native_vector = "
                . "setweight(to_tsvector('simple', coalesce(topic, '')), 'A') "
                . "|| setweight(to_tsvector('simple', coalesce(aliases, '')), 'A') "
                . "|| setweight(to_tsvector(coalesce(topic_desc, '')), 'B') "
                . "|| setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C') "
                . "WHERE source_type = 'factory'" . $topicVectorFilter
            );
        }
        return $restored;
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

    private function executeAffected(string $query): int
    {
        $result = $this->db->execQuery($query);
        if ($result === false) throw new RuntimeException('oghma_catalog_database_write_failed');
        return pg_affected_rows($result);
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

}
