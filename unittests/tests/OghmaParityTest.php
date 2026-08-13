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

    public function testEligibilityNeverRunsOnTimerOrActionFamilies(): void
    {
        foreach (self::$fixture['eligibility_cases'] as $case) {
            $this->assertSame($case['eligible'], chimOghmaRequestEligible([$case['request_type']]), $case['request_type']);
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
        $this->assertSame('skyrim-official-20260813-v1', $package['catalog_version']);
        $this->assertCount(1562, $package['articles']);
        $this->assertSame('245c3ae415ca32689cc3429e106843eea194cf6dae5a87a2ed3105551d30594a', $package['articles_sha256']);
        $this->assertSame('f3751a142161bfc5416c2b6c745d2f8c84bb43ee2ccab966b7f2cba12d366471', $package['manifest_sha256']);
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
        $this->assertSame(hash_file('sha256', dirname(__DIR__) . '/fixtures/oghma-parity-v1.json'), $manifest['reference']['oracle_sha256']);
        foreach ($manifest['algorithm']['files'] as $path => $checksum) {
            $this->assertSame(hash_file('sha256', $root . '/' . $path), $checksum, $path);
        }
        $this->assertSame(hash_file('sha256', $root . '/conf/conf_schema.json'), $manifest['settings']['schema_sha256']);
        $catalogManifest = $root . '/resources/oghma/skyrim-official/catalogs/'
            . $manifest['catalog']['version'] . '/manifest.json';
        $this->assertSame(hash_file('sha256', $catalogManifest), $manifest['catalog']['manifest_sha256']);
        $this->assertSame(25, $manifest['performance']['deterministic_p95_budget_ms']);
    }

    private function catalogDatabase(): object
    {
        return new class(self::$fixture['catalog']) {
            public function __construct(private array $rows) {}
            public function fetchAll(string $query): array { return $this->rows; }
        };
    }
}
