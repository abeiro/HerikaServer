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
    if ($actual !== $case['topics']) {
        $correctnessFailures[] = ['id' => $case['id'], 'expected' => $case['topics'], 'actual' => $actual];
    }
}

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
