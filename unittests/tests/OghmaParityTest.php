<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/lib/oghma_parity.php';
require_once dirname(__DIR__, 2) . '/lib/oghma_retrieval.php';
require_once dirname(__DIR__, 2) . '/lib/oghma_catalog.php';

final class OghmaParityTest extends TestCase
{
    private static array $fixture;
    private array $savedGlobals = [];

    public static function setUpBeforeClass(): void
    {
        $raw = file_get_contents(dirname(__DIR__) . '/fixtures/oghma-parity-v1.json');
        self::$fixture = json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
    }

    protected function setUp(): void
    {
        foreach (['OGHMA_INFINIUM','OGHMA_AMOUNT','OGHMA_RESULT_LIMIT','OGHMA_EXTRACTOR_FALLBACK',
            'OGHMA_EXTRACTOR_TIMEOUT_MS','OGHMA_CUSTOM','CORE_CONNECTOR_OGHMA_CUSTOM',
            'RACIAL_OGHMA','LOCATION_OGHMA','CHIM_CORE_CURRENT_PROFILE_DATA','CHIM_CORE_CURRENT_NPC_DATA'] as $key) {
            $this->savedGlobals[$key] = ['exists' => array_key_exists($key, $GLOBALS), 'value' => $GLOBALS[$key] ?? null];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $key => $saved) {
            if ($saved['exists']) $GLOBALS[$key] = $saved['value'];
            else unset($GLOBALS[$key]);
        }
    }

    public function testFrozenRetrievalContract(): void
    {
        $db = $this->catalogDatabase();
        foreach (self::$fixture['retrieval_cases'] as $case) {
            $result = chimOghmaExtractEntities($db, $case['input'], $case['limit']);
            $topics = array_values(array_column($result['entities'], 'topic'));
            $this->assertSame($case['topics'], $topics, $case['id']);
            foreach ($case['required_rejections'] ?? [] as $reason) {
                $this->assertContains($reason, array_column($result['rejected'], 'reason'), $case['id']);
            }
            foreach ($case['required_tag_decisions'] ?? [] as $reason) {
                $this->assertContains($reason, array_column($result['tag_decisions'], 'reason'), $case['id']);
            }
            if (array_key_exists('fallback_eligible', $case)) {
                $this->assertSame($case['fallback_eligible'], $result['fallback_eligible'], $case['id']);
            }
        }
    }

    public function testSharedStatusVocabulary(): void
    {
        $this->assertSame(self::$fixture['status_vocabulary'], CHIM_OGHMA_STATUSES);
    }

    public function testEligibilityNeverRunsOnTimerOrActionFamilies(): void
    {
        foreach (self::$fixture['eligibility_cases'] as $case) {
            $this->assertSame($case['eligible'], chimOghmaRequestEligible([$case['request_type']]), $case['request_type']);
        }
    }

    public function testFallbackSuggestionsUseTheSharedCatalogIdentityRules(): void
    {
        $db = $this->catalogDatabase();
        foreach (self::$fixture['suggestion_cases'] as $case) {
            $actual = [];
            foreach ($case['suggestions'] as $suggestion) {
                $topic = chimOghmaResolveTopicName($db, $suggestion);
                if ($topic !== null && !in_array($topic, $actual, true)) $actual[] = $topic;
            }
            $this->assertSame($case['topics'], $actual, $case['id']);
        }
    }

    public function testAccessContractIncludesNegativeAndKnowallRules(): void
    {
        foreach (self::$fixture['access_cases'] as $case) {
            $decision = chimOghmaAccessDecision([
                'topic_desc' => 'advanced',
                'topic_desc_basic' => 'basic',
                'knowledge_class' => $case['advanced'],
                'knowledge_class_basic' => $case['basic'],
            ], $case['tags']);
            $this->assertSame($case['level'], $decision['level'], $case['id']);
            $this->assertSame($case['reason'], $decision['reason'], $case['id']);
        }
    }

    public function testNpcKnowledgeTagsRemoveArticleOnlyMarkers(): void
    {
        $this->assertSame(
            'nord, scholar, knowall',
            chimOghmaNpcKnowledgeTags('common|Nord;esoteric, scholar, skyrimall, KNOWALL')
        );
    }

    public function testPackagedCatalogAccessMatrixCoversRepresentativeNpcClasses(): void
    {
        $root = dirname(__DIR__, 2);
        $articles = json_decode(
            (string) file_get_contents($root . '/resources/oghma/skyrim-official/catalogs/skyrim-official-20260814-v2.0/articles.json'),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        $articles = array_column($articles, null, 'topic');
        foreach (self::$fixture['catalog_access_matrix'] as $case) {
            $topic = $case['topics']['chim'];
            $this->assertArrayHasKey($topic, $articles, $case['id']);
            $decision = chimOghmaAccessDecision($articles[$topic], $case['tags']['chim']);
            $this->assertSame($case['level'], $decision['level'], $case['id'] . ':' . $topic);
        }
    }

    public function testEffectiveSettingsReportGlobalProfileAndNpcSources(): void
    {
        $GLOBALS['OGHMA_INFINIUM'] = true;
        $GLOBALS['OGHMA_AMOUNT'] = 3;
        $GLOBALS['OGHMA_RESULT_LIMIT'] = 4;
        $GLOBALS['OGHMA_EXTRACTOR_FALLBACK'] = true;
        $GLOBALS['OGHMA_EXTRACTOR_TIMEOUT_MS'] = 900;
        $GLOBALS['CORE_CONNECTOR_OGHMA_CUSTOM'] = '42';
        $GLOBALS['RACIAL_OGHMA'] = false;
        $GLOBALS['LOCATION_OGHMA'] = true;
        $GLOBALS['CHIM_CORE_CURRENT_PROFILE_DATA'] = ['metadata' => json_encode([
            'CORE_CONNECTOR_OGHMA_CUSTOM' => '42',
            'RACIAL_OGHMA' => false,
        ])];
        $GLOBALS['CHIM_CORE_CURRENT_NPC_DATA'] = ['extended_data' => json_encode([
            'OGHMA_AMOUNT' => 3,
            'OGHMA_RESULT_LIMIT' => 4,
        ])];

        $settings = chimOghmaEffectiveSettings();
        $this->assertSame(CHIM_OGHMA_PARITY_VERSION, $settings['contract']);
        $this->assertSame(3, $settings['values']['topic_count']);
        $this->assertSame(4, $settings['values']['result_limit']);
        $this->assertSame('npc', $settings['sources']['topic_count']);
        $this->assertSame('npc', $settings['sources']['result_limit']);
        $this->assertSame('core_profile', $settings['sources']['connector_id']);
        $this->assertSame('core_profile', $settings['sources']['racial_context_enabled']);
        $this->assertSame('global', $settings['sources']['location_context_enabled']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $settings['sha256']);
    }

    public function testLegacyCustomFlagOnlyActsAsFallbackCompatibility(): void
    {
        unset($GLOBALS['OGHMA_EXTRACTOR_FALLBACK']);
        $GLOBALS['OGHMA_CUSTOM'] = true;
        $GLOBALS['OGHMA_INFINIUM'] = true;
        $settings = chimOghmaEffectiveSettings();
        $this->assertTrue($settings['values']['extractor_fallback_enabled']);
        $this->assertSame('global', $settings['sources']['extractor_fallback_enabled']);
    }

    public function testXmlFragmentIsCanonicalUtf8AndRepresentsDeniedAccess(): void
    {
        $case = self::$fixture['xml_case'];
        $xml = chimOghmaRenderKnowledgeFragment($case['articles'], $case['status']);
        $this->assertStringStartsWith('<oghma contract="oghma-parity-v1" status="grounded">', $xml);
        $this->assertStringContainsString('topic="Méridia &amp; Her Beacon"', $xml);
        $this->assertStringContainsString('Light &lt; darkness &amp; burns &quot;undead&quot;.', $xml);
        $this->assertStringContainsString('<denial reason="negative_class" />', $xml);
        $this->assertSame(1, substr_count($xml, '<content>'));
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function testFallbackSuggestionsMustResolveToOneCatalogOwner(): void
    {
        $db = $this->catalogDatabase();
        $this->assertSame('whiterun', chimOghmaResolveTopicName($db, 'White Run'));
        $this->assertNull(chimOghmaResolveTopicName($db, 'Prince'));
        $this->assertNull(chimOghmaResolveTopicName($db, 'not in catalog'));
    }

    public function testPackagedFactoryCatalogHasFrozenVersionAndChecksums(): void
    {
        $fakeDb = new class {
            public function fetchAll(string $query): array { return []; }
            public function execQuery(string $query): bool { return true; }
        };
        $root = dirname(__DIR__, 2);
        $manager = new ChimOghmaCatalogManager($fakeDb, $root);
        $package = $manager->plan($manager->activePackagePath());
        $this->assertSame('skyrim-official-20260814-v2.0', $package['catalog_version']);
        $this->assertCount(1562, $package['articles']);
        $this->assertSame('d7f2c580e8d2b6eef522f6f80865da2e0ae1d4463f848dc12f7a27bc0c0154fa', $package['articles_sha256']);
        $this->assertSame('6f9e1aa7cb94bbb580707329746fb3b005d66a0b0d41e152043b135b8bb8651e', $package['manifest_sha256']);
    }

    public function testPackagedBasicClassesFollowTheReviewedOntology(): void
    {
        $root = dirname(__DIR__, 2);
        $articles = json_decode(
            (string) file_get_contents($root . '/resources/oghma/skyrim-official/catalogs/skyrim-official-20260814-v2.0/articles.json'),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        $ontology = json_decode(
            (string) file_get_contents($root . '/resources/oghma/skyrim-official/ontology.json'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        $allowed = array_fill_keys($ontology['knowledge_classes'], true);
        $common = 0;
        $esoteric = 0;
        $safeRetrievalPhrases = 0;
        $advancedClassCounts = [];
        $unknown = [];
        foreach ($articles as $article) {
            $advancedClasses = preg_split('/\s*[,;|]\s*/u', (string) $article['knowledge_class'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $basicClasses = preg_split('/\s*[,;|]\s*/u', (string) $article['knowledge_class_basic'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->assertSame([], array_values(array_intersect($advancedClasses, $basicClasses)), $article['topic']);
            $this->assertSame([], array_values(array_intersect(['common', 'esoteric'], $advancedClasses)), $article['topic']);
            if (in_array('common', array_map('strtolower', $basicClasses), true)) $common++;
            if (in_array('esoteric', array_map('strtolower', $basicClasses), true)) $esoteric++;
            $this->assertFalse(
                in_array('common', array_map('strtolower', $basicClasses), true)
                && in_array('esoteric', array_map('strtolower', $basicClasses), true),
                $article['topic']
            );
            foreach ($advancedClasses as $class) {
                $key = strtolower($class);
                $advancedClassCounts[$key] = ($advancedClassCounts[$key] ?? 0) + 1;
            }
            $this->assertNotContains('rift reach', $basicClasses, $article['topic']);
            foreach ($basicClasses as $class) $this->assertArrayHasKey(strtolower($class), $allowed, $article['topic']);
            if (trim((string) $article['retrieval_phrases']) !== '') $safeRetrievalPhrases++;
            if (strtolower(trim((string) $article['knowledge_class'])) === 'blocked') $unknown[] = $article;
        }
        $this->assertSame(1476, $common);
        $this->assertSame(86, $esoteric);
        $this->assertSame(3, $safeRetrievalPhrases);
        $this->assertSame(146, $advancedClassCounts['healer']);
        $this->assertSame(620, $advancedClassCounts['traveler']);
        $this->assertSame(31, $advancedClassCounts['warrior']);
        $this->assertSame(32, $advancedClassCounts['merchant']);
        $this->assertCount(2, $unknown);
        foreach ($unknown as $article) {
            $this->assertSame('basic', chimOghmaAccessDecision($article, [])['level'], $article['topic']);
        }
    }

    public function testContractManifestPinsOracleAlgorithmSettingsAndCatalog(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(
            (string) file_get_contents($root . '/resources/oghma/oghma-parity-v1.manifest.json'),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame(CHIM_OGHMA_PARITY_VERSION, $manifest['contract']);
        $this->assertSame(CHIM_OGHMA_STATUSES, $manifest['runtime']['trace_statuses']);
        $this->assertSame(hash_file('sha256', dirname(__DIR__) . '/fixtures/oghma-parity-v1.json'), $manifest['reference']['oracle_sha256']);
        foreach ($manifest['algorithm']['files'] as $path => $checksum) {
            $this->assertSame(hash_file('sha256', $root . '/' . $path), $checksum, $path);
        }
        $this->assertSame(hash_file('sha256', $root . '/conf/conf_schema.json'), $manifest['settings']['schema_sha256']);
        $this->assertSame('', $manifest['settings']['defaults']['knowledge_tags']);
        $this->assertSame(
            hash_file('sha256', $root . '/resources/oghma/canonical-knowledge-vocabulary-v1.json'),
            $manifest['catalog']['canonical_vocabulary_sha256']
        );
        $catalogManifest = $root . '/resources/oghma/skyrim-official/catalogs/'
            . $manifest['catalog']['version'] . '/manifest.json';
        $this->assertSame(hash_file('sha256', $catalogManifest), $manifest['catalog']['manifest_sha256']);
        $this->assertSame(25, $manifest['performance']['deterministic_p95_budget_ms']);
    }

    public function testEveryFrozenLegacyAliasNormalizesToItsCanonicalTags(): void
    {
        $root = dirname(__DIR__, 2);
        $vocabulary = json_decode(
            (string) file_get_contents($root . '/resources/oghma/canonical-knowledge-vocabulary-v1.json'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        foreach ($vocabulary['legacy_aliases'] as $alias => $canonicalTags) {
            $this->assertSame($canonicalTags, chimOghmaKnowledgeValues($alias), $alias);
        }
    }

    public function testCanonicalAccessAuditCoversEveryPackagedClass(): void
    {
        $root = dirname(__DIR__, 2);
        $articles = json_decode(
            (string) file_get_contents(
                $root . '/resources/oghma/skyrim-official/catalogs/skyrim-official-20260814-v2.0/articles.json'
            ),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
        $articles = array_column($articles, null, 'topic');
        $matrix = json_decode(
            (string) file_get_contents($root . '/docs/evidence/oghma-canonical-vocabulary/access-matrix.json'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        $this->assertCount(84, $matrix['rows']);
        foreach ($matrix['rows'] as $case) {
            $this->assertArrayHasKey($case['topic'], $articles, $case['canonical_tag']);
            $decision = chimOghmaAccessDecision(
                $articles[$case['topic']],
                [$case['canonical_tag']]
            );
            $this->assertSame($case['expected_level'], $decision['level'], $case['canonical_tag']);
        }
    }

    private function catalogDatabase(): object
    {
        return new class(self::$fixture['catalog']) {
            public function __construct(private array $rows) {}
            public function fetchAll(string $query): array { return $this->rows; }
        };
    }
}
