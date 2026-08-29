<?php

ob_start();

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "itt_connector.class.php");

if (!isset($GLOBALS["db"])) {
    $GLOBALS["db"] = new sql();
}

const DESCRIPTION_CREATOR_PROMPT_KEY = 'item_description_creator';

function descriptionCreatorRespond(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function descriptionCreatorStreamFile(string $path, string $downloadName, string $contentType): void
{
    if (!is_file($path)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'File not found'], 404);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function descriptionCreatorDataDir(string $child = ''): string
{
    $base = $GLOBALS["ENGINE_PATH"] . "data" . DIRECTORY_SEPARATOR . "description_creator";
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }

    if ($child === '') {
        return $base;
    }

    $path = $base . DIRECTORY_SEPARATOR . $child;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

function descriptionCreatorCsvDir(): string
{
    return descriptionCreatorDataDir('csv');
}

function descriptionCreatorResolveCsvPath(string $filename): string
{
    $filename = trim($filename);
    if (!preg_match('/^[A-Za-z0-9_.-]+\.csv$/', $filename)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Invalid CSV filename'], 400);
    }

    $path = descriptionCreatorCsvDir() . DIRECTORY_SEPARATOR . $filename;
    $realDir = realpath(descriptionCreatorCsvDir());
    $realPath = realpath($path);
    if ($realDir === false || $realPath === false || strpos($realPath, $realDir . DIRECTORY_SEPARATOR) !== 0) {
        descriptionCreatorRespond(['success' => false, 'error' => 'CSV file not found'], 404);
    }

    return $realPath;
}

function descriptionCreatorCsvFilenameBase(string $plugin): string
{
    $base = trim($plugin);
    if ($base === '') {
        $base = 'all_plugins';
    }

    $base = str_replace(['/', '\\'], '_', $base);
    $base = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $base);
    $base = preg_replace('/_+/', '_', $base);
    $base = trim(strval($base), '._-');
    if ($base === '') {
        $base = 'all_plugins';
    }

    return substr($base, 0, 160);
}

function descriptionCreatorUniqueCsvName(string $plugin): string
{
    $base = descriptionCreatorCsvFilenameBase($plugin) . '_' . date('Y-m-d');
    $dir = descriptionCreatorCsvDir();
    $candidate = $base . '.csv';
    if (!file_exists($dir . DIRECTORY_SEPARATOR . $candidate)) {
        return $candidate;
    }

    for ($i = 2; $i < 100; $i++) {
        $candidate = $base . '_' . $i . '.csv';
        if (!file_exists($dir . DIRECTORY_SEPARATOR . $candidate)) {
            return $candidate;
        }
    }

    return $base . '_' . bin2hex(random_bytes(3)) . '.csv';
}

function descriptionCreatorDeleteCsv(string $filename): array
{
    $path = descriptionCreatorResolveCsvPath($filename);
    $deleted = false;
    if (is_file($path)) {
        $deleted = unlink($path);
    }

    if (!$deleted) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not delete CSV file'], 500);
    }

    return [
        'filename' => basename($path),
        'csvs' => descriptionCreatorListCsvs(),
    ];
}

function descriptionCreatorNormalizeBaseId(string $baseid): string
{
    $baseid = trim($baseid);
    if (preg_match('/^(XX[0-9A-Fa-f]{6}|FEXXX[0-9A-Fa-f]{3}|[0-9A-Fa-f]{8})$/', $baseid)) {
        return strtoupper($baseid);
    }
    return $baseid;
}

function descriptionCreatorUpsertCustomDescription(string $plugin, string $baseid, string $name, string $description): void
{
    $db = $GLOBALS["db"];
    $pluginSql = $db->escapeLiteral($plugin);
    $baseidSql = $db->escapeLiteral($baseid);
    $nameSql = $db->escapeLiteral($name);
    $descriptionSql = $db->escapeLiteral($description);

    $db->execQuery("
        INSERT INTO public.descriptions_custom (
            plugin,
            baseid,
            name,
            description
        )
        VALUES ({$pluginSql}, {$baseidSql}, {$nameSql}, {$descriptionSql})
        ON CONFLICT (plugin, baseid)
        DO UPDATE SET
            name = EXCLUDED.name,
            description = EXCLUDED.description
    ");
}

function descriptionCreatorImportCsv(string $filename): array
{
    $path = descriptionCreatorResolveCsvPath($filename);
    $handle = fopen($path, 'r');
    if (!$handle) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not open CSV file'], 500);
    }

    $header = fgetcsv($handle, 0, ',');
    if (!is_array($header)) {
        fclose($handle);
        descriptionCreatorRespond(['success' => false, 'error' => 'CSV file is empty'], 400);
    }

    $columns = [];
    foreach ($header as $index => $column) {
        $columns[strtolower(trim(strval($column)))] = $index;
    }

    foreach (['plugin', 'baseid', 'name', 'description'] as $requiredColumn) {
        if (!array_key_exists($requiredColumn, $columns)) {
            fclose($handle);
            descriptionCreatorRespond(['success' => false, 'error' => 'Invalid CSV header. Expected: plugin, baseid, name, description.'], 400);
        }
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if ($row === [null]) {
            continue;
        }

        $plugin = trim(strval($row[$columns['plugin']] ?? ''));
        $baseid = descriptionCreatorNormalizeBaseId(strval($row[$columns['baseid']] ?? ''));
        $name = strval($row[$columns['name']] ?? '');
        $description = strval($row[$columns['description']] ?? '');

        if ($baseid === '') {
            $skipped++;
            continue;
        }

        if (strlen($baseid) > 128) {
            $baseid = substr($baseid, 0, 128);
        }

        try {
            descriptionCreatorUpsertCustomDescription($plugin, $baseid, $name, $description);
            $imported++;
        } catch (Throwable $e) {
            $errors[] = [
                'baseid' => $baseid,
                'error' => $e->getMessage(),
            ];
            if (count($errors) > 25) {
                $errors = array_slice($errors, -25);
            }
        }
    }
    fclose($handle);

    return [
        'filename' => basename($path),
        'imported_count' => $imported,
        'skipped_count' => $skipped,
        'error_count' => count($errors),
        'errors' => $errors,
        'csvs' => descriptionCreatorListCsvs(),
    ];
}

function descriptionCreatorCountCsvRows(string $path): int
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        return 0;
    }

    $rows = 0;
    $sawHeader = false;
    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        if (!$sawHeader) {
            $sawHeader = true;
            continue;
        }
        if ($data !== [null] && $data !== false) {
            $rows++;
        }
    }
    fclose($handle);
    return $rows;
}

function descriptionCreatorListCsvs(): array
{
    $files = glob(descriptionCreatorCsvDir() . DIRECTORY_SEPARATOR . '*.csv') ?: [];
    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $output = [];
    foreach ($files as $path) {
        if (!is_file($path)) {
            continue;
        }
        $filename = basename($path);
        $output[] = [
            'filename' => $filename,
            'size' => filesize($path),
            'modified_at' => date('c', filemtime($path)),
            'row_count' => descriptionCreatorCountCsvRows($path),
            'download_url' => 'api/description_creator.php?action=download_csv_file&filename=' . rawurlencode($filename),
            'zip_url' => 'api/description_creator.php?action=download_zip_file&filename=' . rawurlencode($filename),
        ];
    }
    return $output;
}

function descriptionCreatorCsvContents(string $filename, int $limit = 500): array
{
    $path = descriptionCreatorResolveCsvPath($filename);
    $limit = max(1, min(2000, $limit));
    $handle = fopen($path, 'r');
    if (!$handle) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not open CSV file'], 500);
    }

    $header = fgetcsv($handle, 0, ',');
    if (!is_array($header)) {
        $header = [];
    }

    $rows = [];
    $totalRows = 0;
    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        if ($data === [null]) {
            continue;
        }
        $totalRows++;
        if (count($rows) < $limit) {
            $rows[] = $data;
        }
    }
    fclose($handle);

    return [
        'filename' => basename($path),
        'size' => filesize($path),
        'modified_at' => date('c', filemtime($path)),
        'header' => $header,
        'rows' => $rows,
        'row_count' => $totalRows,
        'truncated' => $totalRows > count($rows),
        'download_url' => 'api/description_creator.php?action=download_csv_file&filename=' . rawurlencode(basename($path)),
    ];
}

function descriptionCreatorUpdateCsvDescriptions(string $filename, string $updatesJson): array
{
    $path = descriptionCreatorResolveCsvPath($filename);
    $updates = json_decode($updatesJson, true);
    if (!is_array($updates)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Invalid CSV update payload'], 400);
    }
    if (count($updates) > 2000) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Too many CSV row updates'], 400);
    }

    $updateLookup = [];
    foreach ($updates as $update) {
        if (!is_array($update)) {
            continue;
        }
        $plugin = trim(strval($update['plugin'] ?? ''));
        $baseid = descriptionCreatorNormalizeBaseId(strval($update['baseid'] ?? ''));
        if ($baseid === '') {
            continue;
        }
        $description = strval($update['description'] ?? '');
        $description = preg_replace('/\s+/', ' ', trim($description)) ?? '';
        if (strlen($description) > 5000) {
            $description = substr($description, 0, 5000);
        }
        $updateLookup[$plugin . "\0" . $baseid] = $description;
    }

    if (empty($updateLookup)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'No valid CSV description updates were provided'], 400);
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not open CSV file'], 500);
    }

    $header = fgetcsv($handle, 0, ',');
    if (!is_array($header)) {
        fclose($handle);
        descriptionCreatorRespond(['success' => false, 'error' => 'CSV file is empty'], 400);
    }

    $columns = [];
    foreach ($header as $index => $column) {
        $columns[strtolower(trim(strval($column)))] = $index;
    }
    foreach (['plugin', 'baseid', 'description'] as $requiredColumn) {
        if (!array_key_exists($requiredColumn, $columns)) {
            fclose($handle);
            descriptionCreatorRespond(['success' => false, 'error' => 'Invalid CSV header. Expected plugin, baseid, and description columns.'], 400);
        }
    }

    $rows = [];
    $updated = 0;
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if ($row === [null]) {
            continue;
        }
        $plugin = trim(strval($row[$columns['plugin']] ?? ''));
        $baseid = descriptionCreatorNormalizeBaseId(strval($row[$columns['baseid']] ?? ''));
        $key = $plugin . "\0" . $baseid;
        if (array_key_exists($key, $updateLookup)) {
            $row[$columns['description']] = $updateLookup[$key];
            $updated++;
        }
        $rows[] = $row;
    }
    fclose($handle);

    if ($updated === 0) {
        descriptionCreatorRespond(['success' => false, 'error' => 'No matching CSV rows were found for those updates'], 404);
    }

    $tempPath = $path . '.tmp.' . bin2hex(random_bytes(4));
    $out = fopen($tempPath, 'w');
    if (!$out) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not write updated CSV file'], 500);
    }

    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);

    if (!rename($tempPath, $path)) {
        @unlink($tempPath);
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not replace CSV file'], 500);
    }

    return [
        'filename' => basename($path),
        'updated_count' => $updated,
        'csv' => descriptionCreatorCsvContents(basename($path), 500),
        'csvs' => descriptionCreatorListCsvs(),
    ];
}

function descriptionCreatorSanitizeJobId(string $jobId): string
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', $jobId);
}

function descriptionCreatorJobPath(string $jobId): string
{
    $jobId = descriptionCreatorSanitizeJobId($jobId);
    if ($jobId === '') {
        descriptionCreatorRespond(['success' => false, 'error' => 'Invalid job id'], 400);
    }

    return descriptionCreatorDataDir('jobs') . DIRECTORY_SEPARATOR . $jobId . '.json';
}

function descriptionCreatorLoadJob(string $jobId): array
{
    $path = descriptionCreatorJobPath($jobId);
    if (!is_file($path)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Job not found'], 404);
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Job file is invalid'], 500);
    }

    return $decoded;
}

function descriptionCreatorSaveJob(array $job): void
{
    $job['updated_at'] = date('c');
    $path = descriptionCreatorJobPath(strval($job['job_id'] ?? ''));
    file_put_contents($path, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function descriptionCreatorDefaultPrompt(): string
{
    return "Generate exactly one neutral, factual visual description of the visible item in this image. "
        . "Write one sentence only, ideally 15-25 words. "
        . "Describe only visible physical characteristics: object type, silhouette, material, color, construction, condition, and distinctive details. "
        . "Start directly with the object, material, color, or silhouette. "
        . "Use present tense and clinical wording. "
        . "Do not use conversational or image-framing wording, exclamations, subjective praise, lore, stats, effects, rarity, uncertainty, or phrases like \"Look\", \"I see\", \"This is\", \"These are\", \"This image\", \"The image\", \"showcases\", \"depicts\", \"features\", \"It appears\", or \"seems\". "
        . "Do not use or mention item names, filenames, screenshots, UI, the game, plugins, IDs, or image analysis. "
        . "Output only the description sentence. "
        . "Example: \"A steel sword with a straight double-edged blade, simple crossguard, leather-wrapped grip, and rounded metal pommel.\"";
}

function descriptionCreatorPreviousDefaultPrompts(): array
{
    return [
        "Describe the item shown in this image in one or two short visual sentences. "
            . "Focus on shape, material, color, condition, and distinctive details. "
            . "Use the provided item name only as identification context; do not invent gameplay stats or effects. "
            . "Do not mention UI, screenshots, IDs, plugins, Skyrim, or uncertainty.",
        "Generate exactly one neutral, factual visual description for this item: \"{ITEM_NAME}\". "
            . "Use the item name only to identify the object type. Write one sentence only, ideally 15-25 words. "
            . "Describe only visible physical characteristics: object type, silhouette, material, color, construction, condition, and distinctive details. "
            . "Use present tense and clinical wording. "
            . "Do not use conversational framing, exclamations, subjective praise, lore, stats, effects, rarity, uncertainty, or phrases like \"Look\", \"I see\", \"This is\", \"These are\", \"It appears\", or \"seems\". "
            . "Do not mention screenshots, UI, the game, plugins, IDs, or image analysis. "
            . "Output only the description sentence. "
            . "Example: \"A steel sword with a straight double-edged blade, simple crossguard, leather-wrapped grip, and rounded metal pommel.\"",
        "Generate exactly one neutral, factual visual description of the visible item in this image. "
            . "Write one sentence only, ideally 15-25 words. "
            . "Describe only visible physical characteristics: object type, silhouette, material, color, construction, condition, and distinctive details. "
            . "Use present tense and clinical wording. "
            . "Do not use conversational framing, exclamations, subjective praise, lore, stats, effects, rarity, uncertainty, or phrases like \"Look\", \"I see\", \"This is\", \"These are\", \"It appears\", or \"seems\". "
            . "Do not use or mention item names, filenames, screenshots, UI, the game, plugins, IDs, or image analysis. "
            . "Output only the description sentence. "
            . "Example: \"A steel sword with a straight double-edged blade, simple crossguard, leather-wrapped grip, and rounded metal pommel.\"",
    ];
}

function descriptionCreatorEnsurePromptRow(): void
{
    $db = $GLOBALS["db"];
    $promptKey = $db->escapeLiteral(DESCRIPTION_CREATOR_PROMPT_KEY);
    $defaultPrompt = $db->escapeLiteral(descriptionCreatorDefaultPrompt());
    $description = $db->escapeLiteral('Vision prompt for generating item descriptions from captured Prisma item model images. Supports placeholders: {PLUGIN}, {BASEID}, {IMAGE_FILENAME}. Used in: ui/api/description_creator.php');

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES ({$promptKey}, {$defaultPrompt}, {$description})
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach (descriptionCreatorPreviousDefaultPrompts() as $previousPrompt) {
        $previousDefaultPrompt = $db->escapeLiteral($previousPrompt);
        $db->execQuery("
            UPDATE public.prompts
            SET custom_prompt = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE prompt_key = {$promptKey}
              AND custom_prompt = {$previousDefaultPrompt}
        ");
    }
}

function descriptionCreatorFetchPrompt(): array
{
    descriptionCreatorEnsurePromptRow();

    $db = $GLOBALS["db"];
    $promptKey = $db->escapeLiteral(DESCRIPTION_CREATOR_PROMPT_KEY);
    $row = $db->fetchOne("
        SELECT prompt_key, default_prompt, custom_prompt, description
        FROM public.prompts
        WHERE prompt_key = {$promptKey}
        LIMIT 1
    ");

    $defaultPrompt = strval($row['default_prompt'] ?? descriptionCreatorDefaultPrompt());
    $customPrompt = $row['custom_prompt'] ?? null;
    $activePrompt = trim(strval($customPrompt ?? '')) !== '' ? strval($customPrompt) : $defaultPrompt;

    return [
        'prompt_key' => DESCRIPTION_CREATOR_PROMPT_KEY,
        'default_prompt' => $defaultPrompt,
        'custom_prompt' => $customPrompt,
        'active_prompt' => $activePrompt,
        'description' => strval($row['description'] ?? ''),
    ];
}

function descriptionCreatorSavePrompt(?string $customPrompt): array
{
    descriptionCreatorEnsurePromptRow();

    $db = $GLOBALS["db"];
    $promptKey = $db->escapeLiteral(DESCRIPTION_CREATOR_PROMPT_KEY);
    $customPromptSql = trim(strval($customPrompt ?? '')) === ''
        ? 'NULL'
        : $db->escapeLiteral(strval($customPrompt));

    $db->execQuery("
        UPDATE public.prompts
        SET custom_prompt = {$customPromptSql},
            updated_at = CURRENT_TIMESTAMP
        WHERE prompt_key = {$promptKey}
    ");

    return descriptionCreatorFetchPrompt();
}

function descriptionCreatorPluginCounts(): array
{
    $rows = $GLOBALS["db"]->fetchAll("
        SELECT COALESCE(plugin, '') AS plugin, COUNT(*) AS count
        FROM public.item_images
        GROUP BY COALESCE(plugin, '')
        ORDER BY LOWER(COALESCE(plugin, '')) ASC
    ");

    $total = 0;
    $plugins = [];
    foreach ($rows as $row) {
        $count = intval($row['count'] ?? 0);
        $total += $count;
        $plugins[] = [
            'plugin' => strval($row['plugin'] ?? ''),
            'count' => $count,
        ];
    }

    return [
        'total' => $total,
        'plugins' => $plugins,
    ];
}

function descriptionCreatorImageUrl(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, "/\\"));
    $segments = array_map('rawurlencode', explode('/', $relativePath));
    return '../' . implode('/', $segments);
}

function descriptionCreatorImageTitle(array $item): string
{
    $filename = descriptionCreatorImageFilename($item);

    $name = trim(strval($item['name'] ?? ''));
    if ($name === '') {
        $name = trim(strval($item['baseid'] ?? ''));
    }

    return $filename . ' - ' . $name;
}

function descriptionCreatorImageFilename(array $item): string
{
    $imagePath = str_replace('\\', '/', strval($item['image_path'] ?? ''));
    $filename = basename($imagePath);
    if ($filename === '' || $filename === '.' || $filename === DIRECTORY_SEPARATOR) {
        $filename = strval($item['baseid'] ?? '') . '.jpg';
    }

    return $filename;
}

function descriptionCreatorNormalizeItem(array $row): array
{
    $imagePath = str_replace('\\', '/', strval($row['image_path'] ?? ''));
    $modFolder = basename(dirname($imagePath));
    if ($modFolder === '' || $modFolder === '.' || $modFolder === DIRECTORY_SEPARATOR) {
        $modFolder = strval($row['plugin'] ?? '');
    }

    $item = [
        'plugin' => strval($row['plugin'] ?? ''),
        'baseid' => strval($row['baseid'] ?? ''),
        'name' => strval($row['name'] ?? ''),
        'image_path' => $imagePath,
        'mod_folder' => $modFolder,
        'runtime_formid' => strval($row['runtime_formid'] ?? ''),
        'form_type' => strval($row['form_type'] ?? ''),
    ];
    $item['image_title'] = descriptionCreatorImageTitle($item);
    $item['image_url'] = descriptionCreatorImageUrl($item['image_path']);
    return $item;
}

function descriptionCreatorItemWhereClause(string $plugin): string
{
    $where = "WHERE COALESCE(image_path, '') <> ''";
    if ($plugin !== '') {
        $where .= " AND plugin = " . $GLOBALS["db"]->escapeLiteral($plugin);
    }
    return $where;
}

function descriptionCreatorCountItems(string $plugin = ''): int
{
    $db = $GLOBALS["db"];
    $where = descriptionCreatorItemWhereClause($plugin);
    $row = $db->fetchOne("
        SELECT COUNT(*) AS count
        FROM public.item_images
        {$where}
    ");
    return intval($row['count'] ?? 0);
}

function descriptionCreatorFetchItems(string $plugin = '', int $limit = 0, int $offset = 0): array
{
    $db = $GLOBALS["db"];
    $where = descriptionCreatorItemWhereClause($plugin);

    $limitSql = '';
    if ($limit > 0) {
        $limitSql = ' LIMIT ' . min($limit, 5000);
        if ($offset > 0) {
            $limitSql .= ' OFFSET ' . max(0, $offset);
        }
    }

    $rows = $db->fetchAll("
        SELECT plugin, baseid, COALESCE(NULLIF(name, ''), baseid) AS name, image_path, runtime_formid, form_type
        FROM public.item_images
        {$where}
        ORDER BY LOWER(COALESCE(plugin, '')) ASC, LOWER(COALESCE(NULLIF(name, ''), baseid)) ASC, baseid ASC
        {$limitSql}
    ");

    $items = [];
    foreach ($rows as $row) {
        $items[] = descriptionCreatorNormalizeItem($row);
    }
    return $items;
}

function descriptionCreatorDeleteImage(string $plugin, string $baseid): array
{
    $plugin = trim($plugin);
    $baseid = trim($baseid);
    if ($plugin === '' || $baseid === '') {
        descriptionCreatorRespond(['success' => false, 'error' => 'Plugin and baseid are required'], 400);
    }

    $db = $GLOBALS["db"];
    $escapedPlugin = $db->escapeLiteral($plugin);
    $escapedBaseid = $db->escapeLiteral($baseid);
    $row = $db->fetchOne("
        SELECT plugin, baseid, image_path
        FROM public.item_images
        WHERE plugin = {$escapedPlugin}
          AND baseid = {$escapedBaseid}
        LIMIT 1
    ");

    if (!$row) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Item image was not found'], 404);
    }

    $imagePath = strval($row['image_path'] ?? '');
    $fileDeleted = false;
    $fileMissing = false;
    if ($imagePath !== '') {
        try {
            $resolvedImagePath = descriptionCreatorResolveImagePath($imagePath);
            if (is_file($resolvedImagePath)) {
                $fileDeleted = unlink($resolvedImagePath);
            } else {
                $fileMissing = true;
            }
        } catch (Throwable $e) {
            $fileMissing = true;
        }
    }

    $db->execQuery("
        DELETE FROM public.item_images
        WHERE plugin = {$escapedPlugin}
          AND baseid = {$escapedBaseid}
    ");

    return [
        'plugin' => $plugin,
        'baseid' => $baseid,
        'image_path' => $imagePath,
        'file_deleted' => $fileDeleted,
        'file_missing' => $fileMissing,
    ];
}

function descriptionCreatorResolveImagePath(string $relativePath): string
{
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, "/\\"));
    $candidate = $GLOBALS["ENGINE_PATH"] . $relativePath;
    $real = realpath($candidate);
    $allowedRoot = realpath($GLOBALS["ENGINE_PATH"] . "data" . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . "items");

    if ($real === false || $allowedRoot === false) {
        throw new RuntimeException('Image file was not found');
    }

    if (strpos($real, $allowedRoot . DIRECTORY_SEPARATOR) !== 0 && $real !== $allowedRoot) {
        throw new RuntimeException('Image path is outside item picture storage');
    }

    return $real;
}

function descriptionCreatorEnsureConnectors(): array
{
    $connector = new ITTConnector();
    $rows = $connector->readAll();
    if (empty($rows)) {
        $migrated = $connector->ensureLegacySelectionFromGlobals();
        if ($migrated) {
            $rows = $connector->readAll();
        }
    }

    $activeId = function_exists('chimGetGeneralSettingInt') ? chimGetGeneralSettingInt('GLOBAL_ITT_CONNECTOR_ID', 0) : 0;
    if ($activeId <= 0 && !empty($rows)) {
        $activeId = intval($rows[0]['id'] ?? 0);
    }

    $output = [];
    foreach ($rows as $row) {
        $driver = strval($row['driver'] ?? '');
        $label = trim(strval($row['label'] ?? ''));
        if ($label === '') {
            $label = $connector->getDisplayName($driver) . ' #' . intval($row['id'] ?? 0);
        }
        $output[] = [
            'id' => intval($row['id'] ?? 0),
            'label' => $label,
            'driver' => $driver,
            'active' => intval($row['id'] ?? 0) === $activeId,
        ];
    }

    return [
        'connectors' => $output,
        'active_id' => $activeId,
    ];
}

function descriptionCreatorApplyPromptPlaceholders(string $prompt, array $item): string
{
    return strtr($prompt, [
        '{ITEM_NAME}' => '',
        '{PLUGIN}' => strval($item['plugin'] ?? ''),
        '{BASEID}' => strval($item['baseid'] ?? ''),
        '{IMAGE_TITLE}' => descriptionCreatorImageFilename($item),
        '{IMAGE_FILENAME}' => descriptionCreatorImageFilename($item),
    ]);
}

function descriptionCreatorSentenceIsConversational(string $sentence): bool
{
    return preg_match('/^\s*(?:look\b|i\s+see\b|here(?:\s+is|\s+are)?\b|(?:this|the)\s+(?:image|picture|screenshot)\b)/i', $sentence) === 1;
}

function descriptionCreatorCleanDescriptionSentence(string $sentence): string
{
    $sentence = trim($sentence, " \t\n\r\0\x0B\"'");
    $sentence = preg_replace('/^\s*(?:description|item description)\s*:\s*/i', '', $sentence);
    $sentence = preg_replace('/^(?:this|the)\s+(?:image|picture|screenshot)\s+(?:showcases|shows|depicts|features|presents|displays|contains|captures|highlights|portrays)\s+(?:a|an|the|some)?\s*/i', '', $sentence);
    $sentence = preg_replace('/^(?:shown|pictured|depicted|featured|displayed)\s+(?:is|are)\s+(?:a|an|the|some)?\s*/i', '', $sentence);
    $sentence = preg_replace('/^(?:this\s+is|it\s+is|it\'s)\s+(a|an|the)\s+/i', '$1 ', $sentence);
    $sentence = preg_replace('/^these\s+are\s+(a|an|the)\s+/i', '$1 ', $sentence);
    $sentence = preg_replace('/^these\s+are\s+/i', '', $sentence);
    $sentence = preg_replace('/^i\s+see\s+(a|an|the|some)\s+/i', '$1 ', $sentence);
    $sentence = preg_replace('/^look(?:\s+at)?\s+(?:this|these|the|a|an)?\s*/i', '', $sentence);
    $sentence = preg_replace('/\b(?:screenshot|user interface|UI)\b.*$/i', '', $sentence);
    $sentence = preg_replace('/\s+/', ' ', trim($sentence));

    if ($sentence === '') {
        return '';
    }

    return strtoupper(substr($sentence, 0, 1)) . substr($sentence, 1);
}

function descriptionCreatorNormalizeDescription(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $text = preg_replace('/^\s*[-*]\s+/m', '', $text);
    $text = trim($text, " \t\n\r\0\x0B\"'");
    $text = preg_replace('/\s+/', ' ', $text);

    if ($text === '') {
        return '';
    }

    preg_match_all('/[^.!?]+[.!?]+|[^.!?]+$/', $text, $matches);
    $selected = '';
    $fallback = '';
    foreach ($matches[0] ?? [] as $sentence) {
        $wasConversational = descriptionCreatorSentenceIsConversational($sentence);
        $sentence = descriptionCreatorCleanDescriptionSentence($sentence);
        if ($sentence === '') {
            continue;
        }
        if ($fallback === '') {
            $fallback = $sentence;
        }
        if (!$wasConversational) {
            $selected = $sentence;
            break;
        }
    }

    $text = trim($selected !== '' ? $selected : $fallback);
    if ($text !== '' && !preg_match('/[.!?]$/', $text)) {
        $text .= '.';
    }
    return $text;
}

function descriptionCreatorRunItt(array $job, array $item): string
{
    $connectorId = intval($job['connector_id'] ?? 0);
    if ($connectorId <= 0) {
        throw new RuntimeException('No ITT connector selected');
    }

    $connector = new ITTConnector();
    $connectorRow = $connector->getById($connectorId);
    if (!$connectorRow) {
        throw new RuntimeException('Selected ITT connector was not found');
    }

    $connector->setOldGlobals($connectorRow);
    $driver = strtolower(trim(strval($GLOBALS["ITTFUNCTION"] ?? '')));
    if ($driver === '') {
        throw new RuntimeException('Selected ITT connector has no driver');
    }

    $providerKey = $connector->getProviderKeyFromDriver($driver);
    if ($providerKey === '') {
        throw new RuntimeException('Unsupported ITT connector driver: ' . $driver);
    }

    $prompt = descriptionCreatorApplyPromptPlaceholders(strval($job['prompt'] ?? ''), $item);
    if (!isset($GLOBALS["ITT"]) || !is_array($GLOBALS["ITT"])) {
        $GLOBALS["ITT"] = [];
    }
    if (!isset($GLOBALS["ITT"][$providerKey]) || !is_array($GLOBALS["ITT"][$providerKey])) {
        $GLOBALS["ITT"][$providerKey] = [];
    }
    $GLOBALS["ITT"][$providerKey]["AI_VISION_PROMPT"] = $prompt;
    $GLOBALS["ITT"][$providerKey]["AI_PROMPT"] = 'Item description creator';
    $GLOBALS["ITT"][$providerKey]["max_tokens"] = min(max(64, intval($GLOBALS["ITT"][$providerKey]["max_tokens"] ?? 128)), 256);

    $ittFile = $GLOBALS["ENGINE_PATH"] . "itt" . DIRECTORY_SEPARATOR . "itt-{$driver}.php";
    if (!is_file($ittFile)) {
        throw new RuntimeException('ITT driver file not found: ' . $driver);
    }
    require_once($ittFile);
    if (!function_exists('itt')) {
        throw new RuntimeException('ITT driver did not provide an itt() function');
    }

    $imageFile = descriptionCreatorResolveImagePath(strval($item['image_path'] ?? ''));
    $hints = "Describe the visible item only in exactly one neutral, clinical sentence. "
        . "Start directly with the object, material, color, or silhouette. "
        . "Do not use conversational or image-framing phrases such as Look, I see, This is, These are, This image, The image, showcases, depicts, features, appears, or seems. "
        . "Do not use or mention item names, filenames, UI, screenshots, plugins, IDs, stats, effects, lore, or uncertainty. "
        . "Output only the description sentence.";

    $description = descriptionCreatorNormalizeDescription(strval(itt($imageFile, $hints)));
    if ($description === '') {
        throw new RuntimeException('ITT returned an empty description');
    }

    return $description;
}

function descriptionCreatorStartJob(): void
{
    $connectorId = intval($_POST['connector_id'] ?? 0);
    $plugin = trim(strval($_POST['plugin'] ?? ''));
    $promptInput = $_POST['prompt'] ?? null;
    $promptData = $promptInput !== null && trim(strval($promptInput)) !== ''
        ? descriptionCreatorSavePrompt(strval($promptInput))
        : descriptionCreatorFetchPrompt();

    $items = descriptionCreatorFetchItems($plugin, 0);
    if (empty($items)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'No captured item images were found for this selection'], 400);
    }

    $connector = new ITTConnector();
    if (!$connector->getById($connectorId)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Select a valid ITT vision connector'], 400);
    }

    $jobId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $csvName = descriptionCreatorUniqueCsvName($plugin);
    $csvPath = descriptionCreatorDataDir('csv') . DIRECTORY_SEPARATOR . $csvName;
    $handle = fopen($csvPath, 'w');
    if (!$handle) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not create output CSV'], 500);
    }
    fputcsv($handle, ['plugin', 'baseid', 'name', 'description']);
    fclose($handle);

    $job = [
        'job_id' => $jobId,
        'status' => 'running',
        'connector_id' => $connectorId,
        'plugin' => $plugin,
        'prompt_key' => DESCRIPTION_CREATOR_PROMPT_KEY,
        'prompt' => strval($promptData['active_prompt'] ?? ''),
        'items' => $items,
        'total' => count($items),
        'index' => 0,
        'processed_count' => 0,
        'generated_count' => 0,
        'error_count' => 0,
        'errors' => [],
        'csv_name' => $csvName,
        'csv_path' => $csvPath,
        'zip_name' => null,
        'zip_path' => null,
        'current_title' => '',
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];
    descriptionCreatorSaveJob($job);

    descriptionCreatorRespond([
        'success' => true,
        'job' => descriptionCreatorPublicJob($job),
    ]);
}

function descriptionCreatorPublicJob(array $job): array
{
    unset($job['items'], $job['csv_path'], $job['zip_path']);
    $jobId = strval($job['job_id'] ?? '');
    $job['download_csv_url'] = 'api/description_creator.php?action=download_csv&job_id=' . rawurlencode($jobId);
    $job['download_zip_url'] = 'api/description_creator.php?action=download_zip&job_id=' . rawurlencode($jobId);
    return $job;
}

function descriptionCreatorProcessJob(): void
{
    $job = descriptionCreatorLoadJob(strval($_POST['job_id'] ?? ''));
    if (($job['status'] ?? '') !== 'running') {
        descriptionCreatorRespond(['success' => true, 'job' => descriptionCreatorPublicJob($job)]);
    }

    $batchSize = max(1, min(10, intval($_POST['batch_size'] ?? 2)));
    $csvHandle = fopen(strval($job['csv_path'] ?? ''), 'a');
    if (!$csvHandle) {
        $job['status'] = 'failed';
        $job['errors'][] = ['title' => 'CSV', 'error' => 'Could not open output CSV'];
        $job['error_count'] = intval($job['error_count'] ?? 0) + 1;
        descriptionCreatorSaveJob($job);
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not open output CSV', 'job' => descriptionCreatorPublicJob($job)], 500);
    }

    $items = is_array($job['items'] ?? null) ? $job['items'] : [];
    $total = count($items);
    $processedThisCall = 0;

    while ($processedThisCall < $batchSize && intval($job['index'] ?? 0) < $total) {
        $idx = intval($job['index']);
        $item = $items[$idx];
        $title = descriptionCreatorImageTitle($item);
        $job['current_title'] = $title;

        try {
            $description = descriptionCreatorRunItt($job, $item);
            fputcsv($csvHandle, [
                strval($item['plugin'] ?? ''),
                strval($item['baseid'] ?? ''),
                strval($item['name'] ?? ''),
                $description,
            ]);
            $job['generated_count'] = intval($job['generated_count'] ?? 0) + 1;
        } catch (Throwable $e) {
            $job['error_count'] = intval($job['error_count'] ?? 0) + 1;
            $job['errors'][] = [
                'title' => $title,
                'error' => $e->getMessage(),
            ];
            if (count($job['errors']) > 50) {
                $job['errors'] = array_slice($job['errors'], -50);
            }
            Logger::warn("[description_creator] {$title}: " . $e->getMessage());
        }

        $job['index'] = $idx + 1;
        $job['processed_count'] = intval($job['processed_count'] ?? 0) + 1;
        $processedThisCall++;
        descriptionCreatorSaveJob($job);
    }

    fclose($csvHandle);

    if (intval($job['index'] ?? 0) >= $total) {
        $job['status'] = 'complete';
        $job['current_title'] = '';
        descriptionCreatorSaveJob($job);
    }

    descriptionCreatorRespond([
        'success' => true,
        'job' => descriptionCreatorPublicJob($job),
        'processed_this_call' => $processedThisCall,
    ]);
}

function descriptionCreatorCancelJob(): void
{
    $job = descriptionCreatorLoadJob(strval($_POST['job_id'] ?? ''));
    if (($job['status'] ?? '') === 'running') {
        $job['status'] = 'canceled';
        $job['current_title'] = '';
        descriptionCreatorSaveJob($job);
    }
    descriptionCreatorRespond(['success' => true, 'job' => descriptionCreatorPublicJob($job)]);
}

function descriptionCreatorEnsureZip(array $job): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive is not available');
    }

    $csvPath = strval($job['csv_path'] ?? '');
    if (!is_file($csvPath)) {
        throw new RuntimeException('CSV output was not found');
    }

    $csvBaseName = pathinfo($csvPath, PATHINFO_FILENAME);
    $zipName = 'CHIM_' . descriptionCreatorCsvFilenameBase($csvBaseName) . '.zip';
    $zipPath = descriptionCreatorDataDir('mods') . DIRECTORY_SEPARATOR . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create mod zip');
    }
    $zip->addFile($csvPath, 'CHIM/' . basename($csvPath));
    $zip->close();

    $job['zip_name'] = $zipName;
    $job['zip_path'] = $zipPath;
    descriptionCreatorSaveJob($job);
    return $job;
}

function descriptionCreatorStreamCsvZip(string $filename): void
{
    if (!class_exists('ZipArchive')) {
        descriptionCreatorRespond(['success' => false, 'error' => 'PHP ZipArchive is not available'], 500);
    }

    $csvPath = descriptionCreatorResolveCsvPath($filename);
    $zipName = 'CHIM_' . descriptionCreatorCsvFilenameBase(pathinfo($csvPath, PATHINFO_FILENAME)) . '.zip';
    $zipPath = descriptionCreatorDataDir('mods') . DIRECTORY_SEPARATOR . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Could not create mod zip'], 500);
    }
    $zip->addFile($csvPath, 'CHIM/' . basename($csvPath));
    $zip->close();

    descriptionCreatorStreamFile($zipPath, $zipName, 'application/zip');
}

try {
    $action = trim(strval($_REQUEST['action'] ?? 'state'));

    if ($action === 'download_csv') {
        $job = descriptionCreatorLoadJob(strval($_GET['job_id'] ?? ''));
        descriptionCreatorStreamFile(strval($job['csv_path'] ?? ''), strval($job['csv_name'] ?? 'item_descriptions.csv'), 'text/csv; charset=utf-8');
    }

    if ($action === 'download_csv_file') {
        $filename = strval($_GET['filename'] ?? '');
        $path = descriptionCreatorResolveCsvPath($filename);
        descriptionCreatorStreamFile($path, basename($path), 'text/csv; charset=utf-8');
    }

    if ($action === 'download_zip') {
        $job = descriptionCreatorLoadJob(strval($_GET['job_id'] ?? ''));
        if (!is_file(strval($job['zip_path'] ?? ''))) {
            $job = descriptionCreatorEnsureZip($job);
        }
        descriptionCreatorStreamFile(strval($job['zip_path'] ?? ''), strval($job['zip_name'] ?? 'chim_item_descriptions.zip'), 'application/zip');
    }

    if ($action === 'download_zip_file') {
        descriptionCreatorStreamCsvZip(strval($_GET['filename'] ?? ''));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($action, ['state', 'list', 'list_csvs', 'csv_contents'], true)) {
        descriptionCreatorRespond(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    switch ($action) {
        case 'state':
            $connectorData = descriptionCreatorEnsureConnectors();
            descriptionCreatorRespond([
                'success' => true,
                'prompt' => descriptionCreatorFetchPrompt(),
                'connectors' => $connectorData['connectors'],
                'active_connector_id' => $connectorData['active_id'],
                'counts' => descriptionCreatorPluginCounts(),
                'csvs' => descriptionCreatorListCsvs(),
            ]);
            break;

        case 'list':
            $plugin = trim(strval($_GET['plugin'] ?? ''));
            $fetchAll = isset($_GET['all']) && strval($_GET['all']) === '1';
            $perPageInput = isset($_GET['per_page']) ? intval($_GET['per_page']) : intval($_GET['limit'] ?? 25);
            $total = descriptionCreatorCountItems($plugin);
            if ($fetchAll) {
                $perPage = $total;
                $pages = $total > 0 ? 1 : 0;
                $page = $total > 0 ? 1 : 0;
                $offset = 0;
                $items = descriptionCreatorFetchItems($plugin, 0, 0);
            } else {
                $perPage = max(1, min(25, $perPageInput));
                $pages = $total > 0 ? intval(ceil($total / $perPage)) : 0;
                $page = max(1, intval($_GET['page'] ?? 1));
                if ($pages > 0) {
                    $page = min($page, $pages);
                }
                $offset = ($page - 1) * $perPage;
                $items = descriptionCreatorFetchItems($plugin, $perPage, $offset);
            }
            descriptionCreatorRespond([
                'success' => true,
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages,
            ]);
            break;

        case 'list_csvs':
            descriptionCreatorRespond([
                'success' => true,
                'csvs' => descriptionCreatorListCsvs(),
            ]);
            break;

        case 'csv_contents':
            descriptionCreatorRespond([
                'success' => true,
                'csv' => descriptionCreatorCsvContents(
                    strval($_GET['filename'] ?? ''),
                    intval($_GET['limit'] ?? 500)
                ),
            ]);
            break;

        case 'update_csv_descriptions':
            $result = descriptionCreatorUpdateCsvDescriptions(
                strval($_POST['filename'] ?? ''),
                strval($_POST['updates'] ?? '[]')
            );
            descriptionCreatorRespond([
                'success' => true,
                'filename' => $result['filename'],
                'updated_count' => $result['updated_count'],
                'csv' => $result['csv'],
                'csvs' => $result['csvs'],
            ]);
            break;

        case 'delete_csv':
            $result = descriptionCreatorDeleteCsv(strval($_POST['filename'] ?? ''));
            descriptionCreatorRespond([
                'success' => true,
                'deleted_filename' => $result['filename'],
                'csvs' => $result['csvs'],
            ]);
            break;

        case 'delete_image':
            $result = descriptionCreatorDeleteImage(
                strval($_POST['plugin'] ?? ''),
                strval($_POST['baseid'] ?? '')
            );
            descriptionCreatorRespond([
                'success' => true,
                'deleted' => $result,
                'counts' => descriptionCreatorPluginCounts(),
                'items' => descriptionCreatorFetchItems($result['plugin'], 0, 0),
                'total' => descriptionCreatorCountItems($result['plugin']),
            ]);
            break;

        case 'import_csv':
            $result = descriptionCreatorImportCsv(strval($_POST['filename'] ?? ''));
            descriptionCreatorRespond([
                'success' => true,
                'filename' => $result['filename'],
                'imported_count' => $result['imported_count'],
                'skipped_count' => $result['skipped_count'],
                'error_count' => $result['error_count'],
                'errors' => $result['errors'],
                'csvs' => $result['csvs'],
            ]);
            break;

        case 'save_prompt':
            descriptionCreatorRespond([
                'success' => true,
                'prompt' => descriptionCreatorSavePrompt(strval($_POST['prompt'] ?? '')),
            ]);
            break;

        case 'reset_prompt':
            descriptionCreatorRespond([
                'success' => true,
                'prompt' => descriptionCreatorSavePrompt(null),
            ]);
            break;

        case 'start':
            descriptionCreatorStartJob();
            break;

        case 'process':
            descriptionCreatorProcessJob();
            break;

        case 'cancel':
            descriptionCreatorCancelJob();
            break;

        case 'make_zip':
            $job = descriptionCreatorLoadJob(strval($_POST['job_id'] ?? ''));
            $job = descriptionCreatorEnsureZip($job);
            descriptionCreatorRespond(['success' => true, 'job' => descriptionCreatorPublicJob($job)]);
            break;

        default:
            descriptionCreatorRespond(['success' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    Logger::error("[description_creator] " . $e->getMessage());
    descriptionCreatorRespond([
        'success' => false,
        'error' => $e->getMessage(),
    ], 500);
}
