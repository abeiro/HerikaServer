#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/oghma_parity.php';
require_once dirname(__DIR__) . '/lib/oghma_retrieval.php';

$fixturePath = __DIR__ . '/fixtures/oghma-parity-v1.json';
$articlesPath = dirname(__DIR__) . '/resources/oghma/skyrim-official/catalogs/skyrim-official-20260813-v1/articles.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true, 128, JSON_THROW_ON_ERROR);
$catalog = json_decode((string) file_get_contents($articlesPath), true, 128, JSON_THROW_ON_ERROR);
$iterations = 500;
foreach ($argv as $argument) {
    if (preg_match('/^--iterations=(\d+)$/D', $argument, $matches)) {
        $iterations = max(1, min(10000, intval($matches[1])));
    }
}

$databaseFactory = static fn(array $rows): object => new class($rows) {
    public function __construct(private array $rows) {}
    public function fetchAll(string $query): array { return $this->rows; }
};
$fixtureDb = $databaseFactory($fixture['catalog']);
$catalogDb = $databaseFactory($catalog);

$correctnessFailures = [];
foreach ($fixture['retrieval_cases'] as $case) {
    $extraction = chimOghmaExtractEntities($fixtureDb, (string) $case['input'], intval($case['limit']));
    $actual = array_values(array_column($extraction['entities'], 'topic'));
    $sources = array_values(array_column($extraction['entities'], 'source'));
    $reasons = array_values(array_unique(array_column(array_merge(
        $extraction['tag_decisions'] ?? [], $extraction['rejected'] ?? []
    ), 'reason')));
    if ($actual !== $case['topics']
        || (isset($case['expected_sources']) && $sources !== $case['expected_sources'])
        || (isset($case['required_rejections']) && array_diff($case['required_rejections'], $reasons) !== [])
        || (isset($case['required_tag_decisions']) && array_diff($case['required_tag_decisions'], $reasons) !== [])
        || (array_key_exists('fallback_eligible', $case) && $extraction['fallback_eligible'] !== $case['fallback_eligible'])) {
        $correctnessFailures[] = ['id'=>$case['id'], 'expected'=>$case['topics'], 'actual'=>$actual,
            'expected_sources'=>$case['expected_sources']??null, 'actual_sources'=>$sources, 'actual_reasons'=>$reasons];
    }
}

foreach ($fixture['eligibility_cases'] as $case) {
    $actual = chimOghmaRequestEligible([$case['request_type']]);
    if ($actual !== $case['eligible']) $correctnessFailures[] = ['id'=>'eligibility_'.$case['request_type'], 'expected'=>$case['eligible'], 'actual'=>$actual];
}
foreach ($fixture['access_cases'] as $case) {
    $actual = chimOghmaAccessDecision(['topic_desc'=>'advanced','topic_desc_basic'=>'basic',
        'knowledge_class'=>$case['advanced'],'knowledge_class_basic'=>$case['basic']],$case['tags']);
    if ($actual['level'] !== $case['level'] || $actual['reason'] !== $case['reason']) {
        $correctnessFailures[] = ['id'=>'access_'.$case['id'], 'expected'=>[$case['level'],$case['reason']],
            'actual'=>[$actual['level'],$actual['reason']]];
    }
}
foreach ($fixture['suggestion_cases'] as $case) {
    $actual = [];
    foreach ($case['suggestions'] as $suggestion) {
        $topic = chimOghmaResolveTopicName($fixtureDb, $suggestion);
        if ($topic !== null && !in_array($topic, $actual, true)) $actual[] = $topic;
    }
    if ($actual !== $case['topics']) $correctnessFailures[] = ['id'=>'suggestion_'.$case['id'], 'expected'=>$case['topics'], 'actual'=>$actual];
}
$xmlCase = $fixture['xml_case'];
$promptFragment = chimOghmaRenderKnowledgeFragment($xmlCase['articles'], $xmlCase['status']);
$expectedPrompt = '<oghma contract="oghma-parity-v1" status="grounded">' . "\n"
    . '  <article topic="Méridia &amp; Her Beacon" source="conversation" access="advanced">' . "\n"
    . '    <content>Light &lt; darkness &amp; burns &quot;undead&quot;.</content>' . "\n  </article>\n"
    . '  <article topic="Secret Prince" source="conversation" access="denied">' . "\n"
    . '    <denial reason="negative_class" />' . "\n  </article>\n</oghma>";
if ($promptFragment !== $expectedPrompt) $correctnessFailures[] = ['id'=>'canonical_xml', 'expected'=>$expectedPrompt, 'actual'=>$promptFragment];
if ($fixture['status_vocabulary'] !== CHIM_OGHMA_STATUSES) $correctnessFailures[] = ['id'=>'status_vocabulary'];

$corpus = array_values(array_map(static fn(array $case): string => (string) $case['input'], $fixture['retrieval_cases']));
$coldStarted = hrtime(true);
chimOghmaExtractEntities($catalogDb, $corpus[0], 3);
$coldStartMs = (hrtime(true) - $coldStarted) / 1_000_000;
$durations = [];
for ($index = 0; $index < $iterations; $index++) {
    $input = $corpus[$index % count($corpus)];
    $started = hrtime(true);
    chimOghmaExtractEntities($catalogDb, $input, 3);
    $durations[] = (hrtime(true) - $started) / 1_000_000;
}
sort($durations, SORT_NUMERIC);
$percentile = static function (array $values, float $fraction): float {
    $index = max(0, min(count($values) - 1, (int) ceil(count($values) * $fraction) - 1));
    return round($values[$index], 3);
};
$result = [
    'contract' => CHIM_OGHMA_PARITY_VERSION,
    'catalog_rows' => count($catalog),
    'fixture_cases' => count($fixture['retrieval_cases']),
    'contract_cases' => count($fixture['retrieval_cases']) + count($fixture['eligibility_cases'])
        + count($fixture['access_cases']) + count($fixture['suggestion_cases']) + 2,
    'fixture_sha256' => hash_file('sha256', $fixturePath),
    'prompt_fragment_sha256' => hash('sha256', $promptFragment),
    'iterations' => $iterations,
    'correctness_failures' => $correctnessFailures,
    'cold_start_ms' => round($coldStartMs, 3),
    'latency_ms' => [
        'p50' => $percentile($durations, 0.50),
        'p95' => $percentile($durations, 0.95),
        'p99' => $percentile($durations, 0.99),
        'max' => round(max($durations), 3),
    ],
    'p95_budget_ms' => 25,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($correctnessFailures === [] && $result['latency_ms']['p95'] < 25.0 ? 0 : 1);
