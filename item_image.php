<?php

$path = dirname(__FILE__) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $path;

require_once($path . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
]);
require_once($path . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($path . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "game_plugins.php");

header('Content-Type: application/json');

function chimItemImageRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function chimItemImageParam(string $name, string $default = ''): string
{
    if (!isset($_GET[$name])) {
        return $default;
    }

    return trim((string) $_GET[$name]);
}

function chimItemImageSafePathSegment(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? '';
    $value = trim($value, '._-');
    return $value !== '' ? $value : 'unknown_plugin';
}

function chimItemImageCropRect(): ?array
{
    $cropX = intval(chimItemImageParam('crop_x', '0'));
    $cropY = intval(chimItemImageParam('crop_y', '0'));
    $cropW = intval(chimItemImageParam('crop_w', '0'));
    $cropH = intval(chimItemImageParam('crop_h', '0'));

    if ($cropW <= 0 || $cropH <= 0) {
        return null;
    }

    return [
        'x' => max(0, $cropX),
        'y' => max(0, $cropY),
        'w' => $cropW,
        'h' => $cropH,
    ];
}

function chimItemImageConvertToJpeg(string $sourcePath, string $outputPath, bool $flipVertical, int $targetSize = 768, ?array $cropRect = null): array
{
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($extension === 'jpg' || $extension === 'jpeg') {
        $image = imagecreatefromjpeg($sourcePath);
    } elseif ($extension === 'png') {
        $image = imagecreatefrompng($sourcePath);
    } elseif ($extension === 'gif') {
        $image = imagecreatefromgif($sourcePath);
    } elseif ($extension === 'bmp') {
        $image = imagecreatefrombmp($sourcePath);
    } else {
        throw new RuntimeException("Unsupported uploaded image format: {$extension}");
    }

    if (!$image) {
        throw new RuntimeException("Unable to decode uploaded item image.");
    }

    if ($flipVertical) {
        imageflip($image, IMG_FLIP_VERTICAL);
    }

    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);
    if ($cropRect !== null) {
        $requestedX = intval($cropRect['x']);
        $requestedY = intval($cropRect['y']);
        $requestedW = intval($cropRect['w']);
        $requestedH = intval($cropRect['h']);
        if ($requestedX >= $sourceWidth || $requestedY >= $sourceHeight || $requestedW <= 1 || $requestedH <= 1) {
            $cropRect = null;
        } else {
            $sourceX = min(max(0, $requestedX), max(0, $sourceWidth - 1));
            $sourceY = min(max(0, $requestedY), max(0, $sourceHeight - 1));
            $cropWidth = min(max(1, $requestedW), $sourceWidth - $sourceX);
            $cropHeight = min(max(1, $requestedH), $sourceHeight - $sourceY);
            $cropSize = min($cropWidth, $cropHeight);
            $sourceX += intval(($cropWidth - $cropSize) / 2);
            $sourceY += intval(($cropHeight - $cropSize) / 2);
        }
    }

    if ($cropRect === null) {
        $cropSize = min($sourceWidth, $sourceHeight);
        $sourceX = intval(($sourceWidth - $cropSize) / 2);
        $sourceY = intval(($sourceHeight - $cropSize) / 2);
    }

    $canvas = imagecreatetruecolor($targetSize, $targetSize);
    imagecopyresampled(
        $canvas,
        $image,
        0,
        0,
        $sourceX,
        $sourceY,
        $targetSize,
        $targetSize,
        $cropSize,
        $cropSize
    );

    $quality = intval($GLOBALS["FEATURES"]["MISC"]["ITT_QUALITY"] ?? 90);
    if (!imagejpeg($canvas, $outputPath, $quality)) {
        imagedestroy($image);
        imagedestroy($canvas);
        throw new RuntimeException("Unable to save normalized item image.");
    }

    imagedestroy($image);
    imagedestroy($canvas);

    return [
        'source_width' => $sourceWidth,
        'source_height' => $sourceHeight,
        'width' => $targetSize,
        'height' => $targetSize,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chimItemImageRespond(405, ['ok' => false, 'error' => 'POST required']);
}

if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    Logger::error("[item_image] Missing uploaded file");
    chimItemImageRespond(400, ['ok' => false, 'error' => 'No uploaded image file']);
}

$uploadedSize = intval($_FILES['file']['size'] ?? 0);
if ($uploadedSize <= 0 || $uploadedSize > 25 * 1024 * 1024) {
    Logger::error("[item_image] Invalid uploaded file size: {$uploadedSize}");
    chimItemImageRespond(400, ['ok' => false, 'error' => 'Invalid uploaded image size']);
}

$runtimeFormId = chimNormalizeRuntimeFormId(chimItemImageParam('runtime_formid', chimItemImageParam('formid')));
$plugin = chimNormalizePluginName(chimItemImageParam('plugin'));
$baseid = chimNormalizeLocalFormId(chimItemImageParam('baseid'));
$name = chimItemImageParam('name');
$formType = chimItemImageParam('form_type');
$source = chimItemImageParam('source', 'chim_item_model_capture');

if (($plugin === '' || $baseid === '') && $runtimeFormId !== '') {
    $pluginRow = chimGetLoadedGamePluginByRuntimeFormId($runtimeFormId);
    if ($pluginRow && !empty($pluginRow['plugin_name'])) {
        $plugin = chimNormalizePluginName($pluginRow['plugin_name']);
        $baseid = chimExtractLocalFormIdFromRuntimeFormId($runtimeFormId);
    } elseif (substr($runtimeFormId, 0, 2) === '00') {
        $plugin = 'Skyrim.esm';
        $baseid = $runtimeFormId;
    }
}

if ($plugin === '' || $baseid === '') {
    Logger::error("[item_image] Could not resolve stable item key. plugin='{$plugin}' baseid='{$baseid}' runtime='{$runtimeFormId}'");
    chimItemImageRespond(400, ['ok' => false, 'error' => 'Missing item plugin/baseid metadata']);
}

$uploadFormat = strtolower(chimItemImageParam('format'));
$tempExtension = $uploadFormat === 'png' ? 'png' : 'bmp';
$tempDir = $path . "soundcache";
@mkdir($tempDir, 0777, true);
$tempImage = $tempDir . DIRECTORY_SEPARATOR . "_item_img_" . md5($_FILES['file']['tmp_name'] . microtime(true)) . "." . $tempExtension;

if (!copy($_FILES['file']['tmp_name'], $tempImage)) {
    Logger::error("[item_image] Failed to copy uploaded file to {$tempImage}");
    chimItemImageRespond(500, ['ok' => false, 'error' => 'Unable to store uploaded image']);
}

$safePlugin = chimItemImageSafePathSegment($plugin);
$imageDir = $path . "data" . DIRECTORY_SEPARATOR . "pictures" . DIRECTORY_SEPARATOR . "items" . DIRECTORY_SEPARATOR . $safePlugin;
@mkdir($imageDir, 0777, true);

$imageFilename = $baseid . ".jpg";
$absoluteImagePath = $imageDir . DIRECTORY_SEPARATOR . $imageFilename;
$relativeImagePath = "data/pictures/items/" . $safePlugin . "/" . $imageFilename;
$cropRect = chimItemImageCropRect();

try {
    $flipVertical = $uploadFormat !== 'png';
    $imageInfo = chimItemImageConvertToJpeg($tempImage, $absoluteImagePath, $flipVertical, 768, $cropRect);
} catch (Throwable $e) {
    @unlink($tempImage);
    Logger::error("[item_image] Image conversion failed for {$plugin}|{$baseid}: " . $e->getMessage());
    chimItemImageRespond(500, ['ok' => false, 'error' => 'Unable to convert uploaded image']);
}

@unlink($tempImage);

$db = $GLOBALS["db"] ?? new sql();
$GLOBALS["db"] = $db;

$escapedPlugin = $db->escape($plugin);
$escapedBaseId = $db->escape($baseid);
$escapedName = $db->escape($name);
$escapedRuntimeFormId = $db->escape($runtimeFormId);
$escapedFormType = $db->escape($formType);
$escapedImagePath = $db->escape($relativeImagePath);
$escapedSource = $db->escape($source);
$metadataJson = $db->escape(json_encode([
    'source_width' => $imageInfo['source_width'],
    'source_height' => $imageInfo['source_height'],
    'width' => $imageInfo['width'],
    'height' => $imageInfo['height'],
    'upload_size' => $uploadedSize,
    'capture_context' => chimItemImageParam('capture_context'),
    'batch_index' => chimItemImageParam('batch_index'),
    'batch_total' => chimItemImageParam('batch_total'),
    'crop' => $cropRect,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$db->execQuery("
    INSERT INTO public.item_images (
        plugin,
        baseid,
        name,
        runtime_formid,
        form_type,
        image_path,
        source,
        metadata,
        updated_at
    ) VALUES (
        '{$escapedPlugin}',
        '{$escapedBaseId}',
        '{$escapedName}',
        '{$escapedRuntimeFormId}',
        '{$escapedFormType}',
        '{$escapedImagePath}',
        '{$escapedSource}',
        '{$metadataJson}'::jsonb,
        CURRENT_TIMESTAMP
    )
    ON CONFLICT (plugin, baseid)
    DO UPDATE SET
        name = EXCLUDED.name,
        runtime_formid = EXCLUDED.runtime_formid,
        form_type = EXCLUDED.form_type,
        image_path = EXCLUDED.image_path,
        source = EXCLUDED.source,
        metadata = EXCLUDED.metadata,
        updated_at = CURRENT_TIMESTAMP
");

Logger::info("[item_image] Stored item image {$plugin}|{$baseid} at {$relativeImagePath}");
chimItemImageRespond(200, [
    'ok' => true,
    'plugin' => $plugin,
    'baseid' => $baseid,
    'runtime_formid' => $runtimeFormId,
    'name' => $name,
    'image_path' => $relativeImagePath,
]);
