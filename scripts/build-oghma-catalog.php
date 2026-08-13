<?php

declare(strict_types=1);

const OGHMA_CATALOG_FIELDS = [
    'topic', 'topic_desc', 'native_vector', 'knowledge_class', 'topic_desc_basic',
    'knowledge_class_basic', 'tags', 'category',
];

/** Parse the simple single-row PostgreSQL VALUES form used by the reviewed Oghma seed. */
function parseOghmaSqlValues(string $valueList): array
{
    $values = [];
    $length = strlen($valueList);
    $offset = 0;
    while ($offset < $length) {
        while ($offset < $length && ctype_space($valueList[$offset])) $offset++;
        if ($offset >= $length || $valueList[$offset] !== "'") {
            if (substr($valueList, $offset, 4) === 'NULL') {
                $values[] = '';
                $offset += 4;
            } else {
                throw new RuntimeException('Unsupported Oghma SQL value near byte ' . $offset);
            }
        } else {
            $offset++;
            $value = '';
            while ($offset < $length) {
                if ($valueList[$offset] !== "'") {
                    $value .= $valueList[$offset++];
                    continue;
                }
                if ($offset + 1 < $length && $valueList[$offset + 1] === "'") {
                    $value .= "'";
                    $offset += 2;
                    continue;
                }
                $offset++;
                break;
            }
            $values[] = $value;
        }
        while ($offset < $length && ctype_space($valueList[$offset])) $offset++;
        if ($offset < $length) {
            if ($valueList[$offset] !== ',') throw new RuntimeException('Invalid Oghma SQL separator near byte ' . $offset);
            $offset++;
        }
    }
    return $values;
}

function canonicalOghmaRow(array $row): string
{
    $ordered = [];
    foreach (['topic', 'aliases', 'topic_desc', 'knowledge_class', 'topic_desc_basic', 'knowledge_class_basic', 'tags', 'category'] as $field) {
        $ordered[$field] = (string) ($row[$field] ?? '');
    }
    return json_encode($ordered, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function oghmaCatalogGitFile(string $root, string $reference, string $path): string
{
    if (preg_match('/^[A-Za-z0-9._\/-]+$/D', $reference) !== 1) {
        throw new InvalidArgumentException('Invalid legacy catalog Git reference.');
    }
    $command = 'git -C ' . escapeshellarg($root) . ' show '
        . escapeshellarg($reference . ':' . $path) . ' 2>&1';
    $lines = [];
    $exitCode = 0;
    exec($command, $lines, $exitCode);
    if ($exitCode !== 0) throw new RuntimeException('Could not read legacy factory source: ' . implode("\n", $lines));
    return implode("\n", $lines) . "\n";
}

function oghmaCatalogAliasMapFromCsv(string $csv): array
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $csv);
    rewind($stream);
    $header = fgetcsv($stream);
    if (!is_array($header)) throw new RuntimeException('Oghma alias seed is empty.');
    $header = array_map(static fn($value): string => strtolower(trim((string) $value)), $header);
    $topicIndex = array_search('topic', $header, true);
    $aliasesIndex = array_search('aliases', $header, true);
    if ($topicIndex === false || $aliasesIndex === false) throw new RuntimeException('Oghma alias seed headers are invalid.');
    $aliases = [];
    while (($row = fgetcsv($stream)) !== false) {
        $topic = trim((string) ($row[$topicIndex] ?? ''));
        if ($topic !== '') $aliases[$topic] = trim((string) ($row[$aliasesIndex] ?? ''));
    }
    fclose($stream);
    return $aliases;
}

function oghmaCatalogRowsFromSql(string $sql, array $aliases): array
{
    preg_match_all('/^INSERT INTO public\.oghma VALUES \((.*?)\);\r?$/ms', $sql, $matches, PREG_OFFSET_CAPTURE);
    $articles = [];
    foreach ($matches[1] ?? [] as $match) {
        $values = parseOghmaSqlValues($match[0]);
        if (count($values) !== count(OGHMA_CATALOG_FIELDS)) continue;
        $source = array_combine(OGHMA_CATALOG_FIELDS, $values);
        $topic = trim((string) $source['topic']);
        $article = [
            'topic' => $topic,
            'aliases' => $aliases[$topic] ?? '',
            'topic_desc' => (string) $source['topic_desc'],
            'knowledge_class' => (string) $source['knowledge_class'],
            'topic_desc_basic' => (string) $source['topic_desc_basic'],
            'knowledge_class_basic' => (string) $source['knowledge_class_basic'],
            'tags' => (string) $source['tags'],
            'category' => (string) $source['category'],
        ];
        if ($topic !== '') $articles[] = $article;
    }
    return $articles;
}

$root = dirname(__DIR__);
$version = trim((string) ($argv[1] ?? 'skyrim-official-20260813-v1'));
if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $version) !== 1) {
    throw new InvalidArgumentException('Invalid catalog version.');
}

$sqlPath = $root . '/data/oghma_20250302001.sql';
$aliasPath = $root . '/data/oghma_aliases.csv';
$sql = file_get_contents($sqlPath);
if ($sql === false || !mb_check_encoding($sql, 'UTF-8')) throw new RuntimeException('Oghma seed is unavailable or invalid UTF-8.');

$aliases = [];
$handle = fopen($aliasPath, 'rb');
if ($handle === false) throw new RuntimeException('Oghma alias seed is unavailable.');
$header = fgetcsv($handle);
if (!is_array($header)) throw new RuntimeException('Oghma alias seed is empty.');
$header = array_map(static fn($value): string => strtolower(trim((string) $value)), $header);
$topicIndex = array_search('topic', $header, true);
$aliasesIndex = array_search('aliases', $header, true);
if ($topicIndex === false || $aliasesIndex === false) throw new RuntimeException('Oghma alias seed headers are invalid.');
while (($row = fgetcsv($handle)) !== false) {
    $topic = trim((string) ($row[$topicIndex] ?? ''));
    if ($topic !== '') $aliases[$topic] = trim((string) ($row[$aliasesIndex] ?? ''));
}
fclose($handle);

if (preg_match_all('/^INSERT INTO public\.oghma VALUES \((.*?)\);\r?$/ms', $sql, $matches, PREG_OFFSET_CAPTURE) === false) {
    throw new RuntimeException('Could not parse Oghma INSERT statements.');
}
$articles = [];
foreach ($matches[1] as $match) {
    $lineNumber = substr_count(substr($sql, 0, (int) $match[1]), "\n") + 1;
    $values = parseOghmaSqlValues($match[0]);
    if (count($values) !== count(OGHMA_CATALOG_FIELDS)) {
        throw new RuntimeException('Unexpected Oghma column count at line ' . $lineNumber);
    }
    $source = array_combine(OGHMA_CATALOG_FIELDS, $values);
    $article = [
        'topic' => trim((string) $source['topic']),
        'aliases' => $aliases[trim((string) $source['topic'])] ?? '',
        'topic_desc' => (string) $source['topic_desc'],
        'knowledge_class' => (string) $source['knowledge_class'],
        'topic_desc_basic' => (string) $source['topic_desc_basic'],
        'knowledge_class_basic' => (string) $source['knowledge_class_basic'],
        'tags' => (string) $source['tags'],
        'category' => (string) $source['category'],
    ];
    if ($article['topic'] === '' || $article['topic_desc'] === '') throw new RuntimeException('Oghma topic or description is empty.');
    $article['row_sha256'] = hash('sha256', canonicalOghmaRow($article));
    $articles[$article['topic']] = $article;
}
ksort($articles, SORT_STRING);
if (count($articles) < 1000) throw new RuntimeException('Oghma catalog unexpectedly contains fewer than 1000 rows.');

$directory = $root . '/resources/oghma/skyrim-official/catalogs/' . $version;
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Could not create Oghma catalog directory.');
}
$articlesJson = json_encode(array_values($articles), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
$articlesFile = $directory . '/articles.json';
if (file_put_contents($articlesFile, $articlesJson) === false) throw new RuntimeException('Could not write articles.json.');

$legacyReference = trim((string) ($argv[2] ?? 'a49434ab829168eda0bd1954bbfd4e6751a61d90'));
$legacyRows = oghmaCatalogRowsFromSql(
    oghmaCatalogGitFile($root, $legacyReference, 'data/oghma_20250302001.sql'),
    oghmaCatalogAliasMapFromCsv(oghmaCatalogGitFile($root, $legacyReference, 'data/oghma_aliases.csv'))
);
$legacyChecksums = array_values(array_unique(array_map(
    static fn(array $row): string => hash('sha256', canonicalOghmaRow($row)),
    $legacyRows
)));
sort($legacyChecksums, SORT_STRING);

$manifest = [
    'contract' => 'oghma-parity-v1',
    'format_version' => 1,
    'catalog_version' => $version,
    'game' => 'The Elder Scrolls V: Skyrim',
    'source' => 'HerikaServer reviewed factory catalog',
    'articles_file' => 'articles.json',
    'articles_sha256' => hash('sha256', $articlesJson),
    'row_count' => count($articles),
    'legacy_factory_source' => $legacyReference,
    'legacy_factory_row_count' => count($legacyRows),
    'legacy_factory_row_sha256' => $legacyChecksums,
];
$manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents($directory . '/manifest.json', $manifestJson) === false) throw new RuntimeException('Could not write manifest.json.');
if (file_put_contents(dirname(dirname($directory)) . '/active-catalog-version.txt', $version . "\n") === false) {
    throw new RuntimeException('Could not write active catalog pointer.');
}

echo json_encode([
    'catalog_version' => $version,
    'row_count' => count($articles),
    'articles_sha256' => $manifest['articles_sha256'],
    'manifest_sha256' => hash('sha256', $manifestJson),
    'directory' => $directory,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
