<?php

declare(strict_types=1);

/**
 * Build an immutable Skyrim catalog revision using the frozen canonical vocabulary.
 */

$options = getopt('', ['source:', 'output:', 'catalog-version:', 'ontology::', 'vocabulary::']);
$root = dirname(__DIR__);
$source = rtrim((string) ($options['source'] ?? ''), '/\\');
$output = rtrim((string) ($options['output'] ?? ''), '/\\');
$version = trim((string) ($options['catalog-version'] ?? ''));
$ontologyPath = (string) ($options['ontology'] ?? $root . '/resources/oghma/skyrim-official/ontology.json');
$vocabularyPath = (string) ($options['vocabulary'] ?? $root . '/resources/oghma/canonical-knowledge-vocabulary-v1.json');
if ($source === '' || $output === '' || $version === '') {
    throw new InvalidArgumentException('Required: --source, --output, and --catalog-version');
}
if (file_exists($output)) {
    throw new RuntimeException('Immutable catalog revision already exists: ' . $output);
}

$readJson = static fn(string $path): array => json_decode(
    (string) file_get_contents($path),
    true,
    64,
    JSON_THROW_ON_ERROR
);
$ontology = $readJson($ontologyPath);
$vocabulary = $readJson($vocabularyPath);
$articlesPath = $source . '/articles.json';
$manifestPath = $source . '/manifest.json';
$articles = $readJson($articlesPath);
$manifest = $readJson($manifestPath);
$sourceRaw = (string) file_get_contents($articlesPath);
$sourceNormalizedSha = hash('sha256', str_replace("\r\n", "\n", $sourceRaw));
if (($manifest['articles_sha256'] ?? '') !== $sourceNormalizedSha) {
    throw new RuntimeException('Source catalog is not frozen by its manifest checksum');
}
$sourceVersion = (string) ($manifest['catalog_version'] ?? basename($source));

$canonicalize = static function ($value) use ($vocabulary): array {
    $values = is_array($value) ? $value : preg_split('/\s*[,;|]\s*/u', (string) $value);
    $result = [];
    foreach ($values ?: [] as $item) {
        $item = strtolower(trim((string) $item));
        if ($item === '') continue;
        $negative = str_starts_with($item, '!');
        $key = $negative ? substr($item, 1) : $item;
        foreach ($vocabulary['legacy_aliases'][$key] ?? [$key] as $target) {
            $target = $negative ? '!' . $target : $target;
            if (!in_array($target, $result, true)) $result[] = $target;
        }
    }
    return $result;
};
$organizations = array_values(array_unique(array_merge(
    $vocabulary['shared']['organizations'],
    $vocabulary['product_specific']['chim']['organizations']
)));
$guardTopics = ['dawnstar', 'falkreath', 'markarth', 'morthal', 'riften', 'solitude', 'whiterun', 'windhelm', 'winterhold'];
$allowed = array_fill_keys($ontology['knowledge_classes'], true);
$advancedCounts = [];
$basicCounts = [];
foreach ($articles as &$article) {
    $advanced = $canonicalize($article['knowledge_class'] ?? '');
    $basic = $canonicalize($article['knowledge_class_basic'] ?? '');
    $signal = strtolower(str_replace('_', ' ', implode(' ', array_merge(
        [(string) ($article['topic'] ?? '')],
        (array) ($article['aliases'] ?? []),
        (array) ($article['tags'] ?? [])
    ))));
    foreach ($organizations as $organization) {
        if (str_contains($signal, str_replace('_', ' ', $organization))
            && !in_array($organization, $advanced, true)) {
            $advanced[] = $organization;
        }
    }
    if (in_array($article['topic'] ?? '', $guardTopics, true)
        && !in_array('guard', $advanced, true)) {
        $advanced[] = 'guard';
    }
    foreach (array_merge($advanced, $basic) as $class) {
        $plain = ltrim($class, '!');
        if (!isset($allowed[$plain])) {
            throw new RuntimeException('Unknown canonical class ' . $class . ' for ' . ($article['topic'] ?? ''));
        }
    }
    $article['knowledge_class'] = implode(', ', $advanced);
    $article['knowledge_class_basic'] = implode(', ', $basic);
    $ordered = [];
    foreach ([
        'topic', 'aliases', 'retrieval_phrases', 'topic_desc', 'knowledge_class',
        'topic_desc_basic', 'knowledge_class_basic', 'tags', 'category',
    ] as $field) {
        $ordered[$field] = (string) ($article[$field] ?? '');
    }
    $article['row_sha256'] = hash(
        'sha256',
        json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
    foreach ($advanced as $class) $advancedCounts[$class] = ($advancedCounts[$class] ?? 0) + 1;
    foreach ($basic as $class) $basicCounts[$class] = ($basicCounts[$class] ?? 0) + 1;
}
unset($article);
ksort($advancedCounts);
ksort($basicCounts);

if (!mkdir($output, 0777, true) && !is_dir($output)) {
    throw new RuntimeException('Unable to create output directory: ' . $output);
}
$encoded = json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($output . '/articles.json', $encoded . PHP_EOL);
$manifest['catalog_version'] = $version;
$manifest['articles_sha256'] = hash_file('sha256', $output . '/articles.json');
$manifest['ontology_sha256'] = hash_file('sha256', $ontologyPath);
$manifest['canonical_vocabulary_sha256'] = hash_file('sha256', $vocabularyPath);
$manifest['builder_sha256'] = hash_file('sha256', __FILE__);
$manifest['advanced_class_counts'] = $advancedCounts;
$manifest['basic_class_counts'] = $basicCounts;
$manifest['classification_revision'] = [
    'source_catalog_version' => $sourceVersion,
    'source_manifest_sha256' => hash_file('sha256', $manifestPath),
    'source_articles_sha256' => $sourceNormalizedSha,
    'policy' => 'frozen canonical vocabulary plus explicit organization and guard signals',
];
file_put_contents(
    $output . '/manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
);
file_put_contents($root . '/resources/oghma/skyrim-official/active-catalog-version.txt', $version . PHP_EOL);
printf(
    "catalog=%s rows=%d articles_sha256=%s vocabulary_sha256=%s\n",
    $version,
    count($articles),
    hash_file('sha256', $output . '/articles.json'),
    hash_file('sha256', $vocabularyPath)
);
