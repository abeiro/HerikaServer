<?php

declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once dirname(__DIR__, 2) . '/lib/oghma_catalog.php';

final class OghmaCatalogTest extends DatabaseTestCase
{
    private string $temporaryRoot = '';

    public function tearDown(): void
    {
        parent::tearDown();
        if ($this->temporaryRoot !== '' && is_dir($this->temporaryRoot)) {
            foreach (glob($this->temporaryRoot . DIRECTORY_SEPARATOR . '*') ?: [] as $directory) {
                foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) @unlink($file);
                @rmdir($directory);
            }
            @rmdir($this->temporaryRoot);
        }
    }

    public function testCurrentDatasetSyncReplacesFactoryAndPreservesCustom(): void
    {
        $fixture = $this->fixture()['catalog_lifecycle'];
        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-oghma-parity-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryRoot, 0700, true);
        $retiredFactory = [
            'topic' => 'retired_factory_fixture',
            'aliases' => '',
            'topic_desc' => 'Factory prose retired by the curated catalog.',
            'knowledge_class' => '',
            'topic_desc_basic' => '',
            'knowledge_class_basic' => '',
            'tags' => 'fixture',
            'category' => 'items',
        ];
        $v1 = $this->writePackage($fixture['v1'], $fixture['v1_articles'], false, [$retiredFactory]);
        $v2 = $this->writePackage($fixture['v2'], $fixture['v2_articles'], false, [], 2);

        require_once dirname(__DIR__, 2) . '/lib/phpunit.class.php';
        $db = new sql();
        $db->execQuery('DELETE FROM public.oghma');
        $legacyValues = array_map(fn(string $value): string => $db->escapeLiteral($value), array_values($retiredFactory));
        $db->execQuery(
            'INSERT INTO public.oghma (topic, aliases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic, tags, category, source_type) VALUES ('
            . implode(', ', $legacyValues) . ", 'legacy')"
        );
        $manager = new ChimOghmaCatalogManager($db, dirname(__DIR__, 2));
        $first = $manager->synchronize($v1);
        $this->assertSame(2, $first['projected']);
        $this->assertSame(1, $first['legacy_factory_classified']);
        $retiredCount = $db->fetchOne("SELECT count(*) AS count FROM public.oghma WHERE topic = 'retired_factory_fixture'");
        $this->assertSame(0, intval($retiredCount['count'] ?? -1));

        $db->execQuery(
            "UPDATE public.oghma SET topic_desc = " . $db->escapeLiteral($fixture['custom_content'])
            . ", source_type = 'custom', source_catalog_version = NULL, source_checksum = " . $db->escapeLiteral(str_repeat('a', 64))
            . " WHERE topic = " . $db->escapeLiteral($fixture['custom_topic'])
        );
        $second = $manager->synchronize($v2);
        $this->assertSame(1, $second['custom_collisions']);
        $this->assertProjection($db, $fixture['expected_after_v2']);
        $v2Factory = $db->fetchOne("SELECT retrieval_phrases FROM public.oghma WHERE topic = 'meridia'");
        $this->assertSame('Colored Rooms', $v2Factory['retrieval_phrases'] ?? null);

        $this->assertSame('synchronized', $second['status']);
        $catalogs = $db->fetchAll('SELECT catalog_version, state, previous_catalog_version FROM public.oghma_catalogs');
        $this->assertSame([[
            'catalog_version' => $fixture['v2'],
            'state' => 'active',
            'previous_catalog_version' => null,
        ]], $catalogs);
        $events = $db->fetchOne('SELECT count(*) AS count FROM public.oghma_catalog_events');
        $this->assertSame(0, intval($events['count'] ?? -1));

        $custom = $db->fetchOne("SELECT source_type, source_catalog_version FROM public.oghma WHERE topic = 'whiterun'");
        $this->assertSame('custom', $custom['source_type']);
        $this->assertSame('', (string) ($custom['source_catalog_version'] ?? ''));
        $db->close();
    }

    public function testDeletingCustomOverridesImmediatelyRestoresFactoryArticles(): void
    {
        $fixture = $this->fixture()['catalog_lifecycle'];
        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-oghma-parity-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryRoot, 0700, true);
        $v2 = $this->writePackage($fixture['v2'], $fixture['v2_articles'], false, [], 2);

        require_once dirname(__DIR__, 2) . '/lib/phpunit.class.php';
        $db = new sql();
        $db->execQuery('DELETE FROM public.oghma');
        $manager = new ChimOghmaCatalogManager($db, dirname(__DIR__, 2));
        $manager->synchronize($v2);

        $db->execQuery(
            "UPDATE public.oghma SET topic_desc = " . $db->escapeLiteral($fixture['custom_content'])
            . ", source_type = 'custom', source_catalog_version = NULL WHERE topic = 'whiterun'"
        );
        $sourceBeforeDelete = $db->fetchOne(
            "SELECT topic_desc FROM public.oghma_catalog_entries WHERE catalog_version = "
            . $db->escapeLiteral($fixture['v2']) . " AND topic = 'whiterun'"
        );
        $this->assertSame('Factory Whiterun v2', $sourceBeforeDelete['topic_desc'] ?? null);

        $single = $manager->deleteCustomOverride('whiterun');
        $this->assertSame(['deleted' => 1, 'factory_restored' => 1], $single);
        $restored = $db->fetchOne(
            "SELECT topic_desc, retrieval_phrases, source_type, source_catalog_version, native_vector IS NOT NULL AS has_vector "
            . "FROM public.oghma WHERE topic = 'whiterun'"
        );
        $this->assertSame('Factory Whiterun v2', $restored['topic_desc'] ?? null);
        $this->assertSame('Cloud District', $restored['retrieval_phrases'] ?? null);
        $this->assertSame('factory', $restored['source_type'] ?? null);
        $this->assertSame($fixture['v2'], $restored['source_catalog_version'] ?? null);
        $this->assertSame('t', $restored['has_vector'] ?? null);

        $db->execQuery("UPDATE public.oghma SET source_type = 'custom', source_catalog_version = NULL WHERE topic = 'whiterun'");
        $db->execQuery(
            "INSERT INTO public.oghma (topic, topic_desc, source_type) VALUES ('standalone_custom', 'Custom only', 'custom')"
        );
        $all = $manager->deleteAllCustomOverrides();
        $this->assertSame(['deleted' => 2, 'factory_restored' => 1], $all);
        $projection = $db->fetchAll('SELECT topic, source_type FROM public.oghma ORDER BY topic');
        $this->assertSame([
            ['topic' => 'meridia', 'source_type' => 'factory'],
            ['topic' => 'whiterun', 'source_type' => 'factory'],
        ], $projection);
        $db->close();
    }

    public function testCorruptPackageCannotChangeActiveCatalog(): void
    {
        $fixture = $this->fixture()['catalog_lifecycle'];
        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-oghma-parity-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryRoot, 0700, true);
        $valid = $this->writePackage($fixture['v1'], $fixture['v1_articles']);
        $corrupt = $this->writePackage('fixture-corrupt-v1', $fixture['v2_articles'], true, [], 2);

        require_once dirname(__DIR__, 2) . '/lib/phpunit.class.php';
        $db = new sql();
        $db->execQuery('DELETE FROM public.oghma');
        $manager = new ChimOghmaCatalogManager($db, dirname(__DIR__, 2));
        $manager->synchronize($valid);
        try {
            $manager->synchronize($corrupt);
            $this->fail('Corrupt package was accepted.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame('oghma_articles_checksum_mismatch', $error->getMessage());
        }
        $active = $manager->activeCatalog();
        $this->assertSame($fixture['v1'], $active['catalog_version']);
        $db->close();
    }

    public function testCatalogRejectsLegacyAliasSeparators(): void
    {
        $fixture = $this->fixture()['catalog_lifecycle'];
        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-oghma-parity-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryRoot, 0700, true);
        $rows = $fixture['v1_articles'];
        $rows[0]['aliases'] = 'Legacy Pipe|Second Alias';
        $package = $this->writePackage('fixture-invalid-aliases-v1', $rows);

        require_once dirname(__DIR__, 2) . '/lib/phpunit.class.php';
        $db = new sql();
        $manager = new ChimOghmaCatalogManager($db, dirname(__DIR__, 2));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_oghma_alias_separator_');
        try {
            $manager->synchronize($package);
        } finally {
            $db->close();
        }
    }

    private function writePackage(
        string $version,
        array $rows,
        bool $corrupt = false,
        array $legacyFactoryRows = [],
        int $formatVersion = 1
    ): string
    {
        $directory = $this->temporaryRoot . DIRECTORY_SEPARATOR . $version;
        mkdir($directory, 0700, true);
        $articles = [];
        foreach ($rows as $row) {
            $ordered = [];
            $fields = $formatVersion === 2
                ? ['topic','aliases','retrieval_phrases','topic_desc','knowledge_class','topic_desc_basic','knowledge_class_basic','tags','category']
                : ['topic','aliases','topic_desc','knowledge_class','topic_desc_basic','knowledge_class_basic','tags','category'];
            foreach ($fields as $field) {
                $ordered[$field] = (string) ($row[$field] ?? '');
            }
            $ordered['row_sha256'] = hash('sha256', json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $articles[] = $ordered;
        }
        $articlesJson = json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'articles.json', $articlesJson);
        $manifest = [
            'contract' => CHIM_OGHMA_PARITY_VERSION,
            'format_version' => $formatVersion,
            'catalog_version' => $version,
            'articles_file' => 'articles.json',
            'articles_sha256' => $corrupt ? str_repeat('0', 64) : hash('sha256', $articlesJson),
            'row_count' => count($articles),
            'legacy_factory_row_sha256' => array_map(function (array $row): string {
                $ordered = [];
                foreach (['topic','aliases','topic_desc','knowledge_class','topic_desc_basic','knowledge_class_basic','tags','category'] as $field) {
                    $ordered[$field] = (string) ($row[$field] ?? '');
                }
                return hash('sha256', json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }, $legacyFactoryRows),
        ];
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        return $directory;
    }

    private function assertProjection(sql $db, array $expected): void
    {
        foreach ($expected as $topic => $description) {
            $row = $db->fetchOne('SELECT topic_desc FROM public.oghma WHERE topic = ' . $db->escapeLiteral($topic));
            $this->assertSame($description, $row['topic_desc'] ?? null, $topic);
        }
    }

    private function fixture(): array
    {
        $raw = file_get_contents(dirname(__DIR__) . '/fixtures/oghma-parity-v1.json');
        return json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
    }
}
