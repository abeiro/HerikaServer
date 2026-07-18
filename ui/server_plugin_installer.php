<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$pluginRepositoryFile = __DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "plugin_repository.json";
$pluginRepository = [];
if (file_exists($pluginRepositoryFile)) {
    $repositoryData = json_decode(file_get_contents($pluginRepositoryFile), true);
    if (is_array($repositoryData) && isset($repositoryData["plugins"]) && is_array($repositoryData["plugins"])) {
        $pluginRepository = $repositoryData["plugins"];
    }
}

function chimPluginInstallerEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function chimPluginInstallerFetchUrl($url) {
    if (!function_exists("curl_init")) {
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: CHIM Plugin Installer\r\nAccept: application/vnd.github.v3+json, application/json, */*\r\n",
                "timeout" => 60,
            ],
        ]);
        return @file_get_contents($url, false, $context);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "CHIM Plugin Installer");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/vnd.github.v3+json, application/json, */*"]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return false;
    }
    return $response;
}

function chimPluginInstallerReadJson($path) {
    if (!file_exists($path)) {
        return false;
    }
    $data = json_decode(file_get_contents($path), true);
    return json_last_error() === JSON_ERROR_NONE ? $data : false;
}

function chimPluginInstallerStringEndsWith($value, $suffix) {
    if ($suffix === "") {
        return true;
    }
    return substr($value, -strlen($suffix)) === $suffix;
}

function chimPluginInstallerFindRepositoryEntry($pluginRepository, $pluginId, $packageName, $githubRepo) {
    if ($pluginId !== "" && isset($pluginRepository[$pluginId]) && is_array($pluginRepository[$pluginId])) {
        $entry = $pluginRepository[$pluginId];
        $entry["_plugin_id"] = $pluginId;
        return $entry;
    }

    foreach ($pluginRepository as $id => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryName = $entry["name"] ?? "";
        $entryRepo = $entry["git_repo"] ?? "";
        if (($packageName !== "" && $entryName === $packageName) || ($githubRepo !== "" && $entryRepo === $githubRepo)) {
            $entry["_plugin_id"] = $id;
            return $entry;
        }
    }

    return false;
}

function chimPluginInstallerReplaceTokens($value, $packageName, $githubRepo, $channelId, $branch) {
    return strtr($value, [
        "<package>" => $packageName,
        "<repo>" => $githubRepo,
        "<channel>" => $channelId,
        "<branch>" => $branch,
    ]);
}

function chimPluginInstallerNormalizeChannels($entry, $packageName, $githubRepo) {
    $channels = [];
    $rawChannels = $entry["channels"] ?? [];

    if (is_array($rawChannels) && !empty($rawChannels)) {
        foreach ($rawChannels as $channelId => $channelConfig) {
            if (is_string($channelConfig)) {
                $channelConfig = ["branch" => $channelConfig];
            }
            if (!is_array($channelConfig)) {
                continue;
            }

            $branch = (string)($channelConfig["branch"] ?? $channelId);
            $label = (string)($channelConfig["label"] ?? ucfirst((string)$channelId));
            $manifestUrl = (string)($channelConfig["manifest_url"] ?? "");
            if ($manifestUrl === "" && $branch !== "") {
                $manifestUrl = "https://raw.githubusercontent.com/" . $githubRepo . "/" . $branch . "/manifest.json";
            }

            $packageUrls = [];
            if (isset($channelConfig["package_urls"]) && is_array($channelConfig["package_urls"])) {
                $packageUrls = $channelConfig["package_urls"];
            } elseif (isset($channelConfig["package_url"])) {
                $packageUrls = [$channelConfig["package_url"]];
            }

            if (empty($packageUrls)) {
                $packageSource = $channelConfig["package_source"] ?? "";
                if ($packageSource === "branch" || (!in_array($channelId, ["main", "live", "stable"], true) && $branch !== "")) {
                    $packageUrls = ["https://github.com/" . $githubRepo . "/archive/refs/heads/" . $branch . ".tar.gz"];
                } else {
                    $packageUrls = [
                        "https://github.com/" . $githubRepo . "/releases/latest/download/" . $packageName . ".tar.gz",
                        "https://github.com/" . $githubRepo . "/releases/latest/download/" . $packageName . ".tar",
                    ];
                }
            }

            $packageUrls = array_map(function ($url) use ($packageName, $githubRepo, $channelId, $branch) {
                return chimPluginInstallerReplaceTokens((string)$url, $packageName, $githubRepo, (string)$channelId, $branch);
            }, $packageUrls);

            $channels[$channelId] = [
                "id" => (string)$channelId,
                "label" => $label,
                "branch" => $branch,
                "manifest_url" => chimPluginInstallerReplaceTokens($manifestUrl, $packageName, $githubRepo, (string)$channelId, $branch),
                "package_urls" => $packageUrls,
                "archive_strip_components" => (int)($channelConfig["archive_strip_components"] ?? 1),
                "allow_force" => (bool)($channelConfig["allow_force"] ?? ($channelId !== "main")),
            ];
        }
    }

    if (empty($channels)) {
        $channels["main"] = [
            "id" => "main",
            "label" => "Live",
            "branch" => "",
            "manifest_url" => "https://api.github.com/repos/" . $githubRepo . "/contents/manifest.json",
            "package_urls" => [
                "https://github.com/" . $githubRepo . "/releases/latest/download/" . $packageName . ".tar.gz",
                "https://github.com/" . $githubRepo . "/releases/latest/download/" . $packageName . ".tar",
            ],
            "archive_strip_components" => 1,
            "allow_force" => false,
        ];
    }

    return $channels;
}

function chimPluginInstallerGetRemoteManifest($channel, $githubRepo) {
    $manifestUrl = $channel["manifest_url"] ?? "";
    if ($manifestUrl === "" && !empty($channel["branch"])) {
        $manifestUrl = "https://api.github.com/repos/" . $githubRepo . "/contents/manifest.json?ref=" . rawurlencode($channel["branch"]);
    }
    if ($manifestUrl === "") {
        $manifestUrl = "https://api.github.com/repos/" . $githubRepo . "/contents/manifest.json";
    }

    $response = chimPluginInstallerFetchUrl($manifestUrl);
    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    if (isset($data["content"])) {
        $content = base64_decode($data["content"]);
        $manifest = json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE ? $manifest : false;
    }

    return is_array($data) ? $data : false;
}

function chimPluginInstallerRemoveDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === "." || $item === "..") {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            chimPluginInstallerRemoveDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function chimPluginInstallerEnsurePluginName($packageName) {
    return preg_match('/^[A-Za-z0-9_.-]+$/', $packageName) === 1;
}

function chimPluginInstallerEnsureGithubRepo($githubRepo) {
    return preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $githubRepo) === 1;
}

function chimPluginInstallerConnectToDatabase() {
    $connStr = "host=localhost port=5432 dbname=dwemer user=dwemer password=dwemer";
    $conn = pg_connect($connStr);
    if (!$conn) {
        throw new Exception("Failed to connect to database: " . pg_last_error());
    }
    return $conn;
}

function chimPluginInstallerRunMigrations($targetDir, $packageName) {
    $migrationsDir = $targetDir . DIRECTORY_SEPARATOR . "migrations";
    if (!is_dir($migrationsDir)) {
        echo "<p class='log-info'>No migrations directory found, skipping database migrations.</p>\n";
        return true;
    }

    try {
        $conn = chimPluginInstallerConnectToDatabase();
        pg_query($conn, "CREATE SCHEMA IF NOT EXISTS plugins");
        pg_query($conn, "CREATE TABLE IF NOT EXISTS plugins.plugin_migrations (plugin_name VARCHAR(255), migration_name VARCHAR(255), executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (plugin_name, migration_name))");

        $migrations = glob($migrationsDir . DIRECTORY_SEPARATOR . "*.sql");
        if (empty($migrations)) {
            echo "<p class='log-info'>No migration files found.</p>\n";
            pg_close($conn);
            return true;
        }

        sort($migrations);
        foreach ($migrations as $migrationFile) {
            $migrationName = basename($migrationFile);
            $result = pg_query_params($conn, "SELECT 1 FROM plugins.plugin_migrations WHERE plugin_name = $1 AND migration_name = $2", [$packageName, $migrationName]);
            if ($result && pg_num_rows($result) > 0) {
                echo "<p class='log-skipped'>Skipping already executed migration: " . chimPluginInstallerEscape($migrationName) . "</p>\n";
                continue;
            }

            echo "<p class='log-running'>Running migration: " . chimPluginInstallerEscape($migrationName) . "</p>\n";
            $sql = file_get_contents($migrationFile);
            $migrationResult = pg_query($conn, $sql);
            if ($migrationResult === false) {
                throw new Exception("Migration failed: " . $migrationName . " - " . pg_last_error($conn));
            }
            pg_query_params($conn, "INSERT INTO plugins.plugin_migrations (plugin_name, migration_name) VALUES ($1, $2)", [$packageName, $migrationName]);
            echo "<p class='log-completed'>Migration completed: " . chimPluginInstallerEscape($migrationName) . "</p>\n";
        }

        pg_close($conn);
        return true;
    } catch (Exception $e) {
        echo "<p class='log-error'>Error running migrations: " . chimPluginInstallerEscape($e->getMessage()) . "</p>\n";
        return false;
    }
}

function chimPluginInstallerRunComposer($targetDir) {
    $composerJson = $targetDir . DIRECTORY_SEPARATOR . "composer.json";
    if (!file_exists($composerJson)) {
        echo "<p class='log-info'>No composer.json found, skipping dependency installation.</p>\n";
        return true;
    }

    echo "<p class='log-action'>Installing dependencies with Composer...</p>\n";
    $installCmd = "cd " . escapeshellarg($targetDir) . " && COMPOSER_HOME=" . escapeshellarg(sys_get_temp_dir()) . " /usr/bin/composer --no-ansi -v install";
    ob_start();
    system($installCmd, $installStatus);
    $installOutput = ob_get_clean();
    echo "<div class='system-command-output'>" . nl2br(chimPluginInstallerEscape($installOutput)) . "</div>";

    if ($installStatus !== 0) {
        throw new Exception("Composer install failed with status " . $installStatus);
    }
    return true;
}

function chimPluginInstallerDownloadPackage($channel, $targetParent, $packageName) {
    foreach ($channel["package_urls"] as $packageUrl) {
        echo "<p class='log-action'>Trying package URL: " . chimPluginInstallerEscape($packageUrl) . "</p>\n";
        $downloadContent = chimPluginInstallerFetchUrl($packageUrl);
        if ($downloadContent === false) {
            echo "<p class='log-skipped'>Download failed, trying next URL if available.</p>\n";
            continue;
        }

        $packagePath = parse_url($packageUrl, PHP_URL_PATH) ?? "";
        $extension = chimPluginInstallerStringEndsWith($packagePath, ".tar") ? ".tar" : ".tar.gz";
        $archiveFile = $targetParent . DIRECTORY_SEPARATOR . "." . $packageName . "-download-" . uniqid("", true) . $extension;
        if (@file_put_contents($archiveFile, $downloadContent) === false) {
            throw new Exception("Failed to write downloaded archive.");
        }
        return [$archiveFile, $packageUrl];
    }

    throw new Exception("Failed to download package from all configured channel URLs.");
}

function chimPluginInstallerInstallPackage($channel, $targetDir, $packageName, $githubRepo) {
    $targetParent = dirname($targetDir);
    if (!is_dir($targetParent) || !is_writable($targetParent)) {
        throw new Exception("Target parent is not writable: " . $targetParent);
    }

    [$archiveFile, $downloadedUrl] = chimPluginInstallerDownloadPackage($channel, $targetParent, $packageName);
    $stagingDir = $targetParent . DIRECTORY_SEPARATOR . "." . $packageName . "-install-" . uniqid("", true);
    if (!mkdir($stagingDir, 0755, true)) {
        @unlink($archiveFile);
        throw new Exception("Failed to create staging directory.");
    }

    $isGzip = !chimPluginInstallerStringEndsWith($archiveFile, ".tar");
    $tarFlags = $isGzip ? "xvfz" : "xvf";
    $stripComponents = max(0, (int)($channel["archive_strip_components"] ?? 1));
    $extractCmd = "tar " . $tarFlags . " " . escapeshellarg($archiveFile) . " -C " . escapeshellarg($stagingDir) . " --strip-components=" . $stripComponents;

    echo "<p class='log-action'>Extracting package...</p>\n";
    ob_start();
    system($extractCmd, $extractStatus);
    $extractOutput = ob_get_clean();
    echo "<div class='system-command-output'>" . nl2br(chimPluginInstallerEscape($extractOutput)) . "</div>";
    @unlink($archiveFile);

    if ($extractStatus !== 0) {
        chimPluginInstallerRemoveDirectory($stagingDir);
        throw new Exception("Failed to extract archive from " . $downloadedUrl);
    }

    $manifestPath = $stagingDir . DIRECTORY_SEPARATOR . "manifest.json";
    $manifest = chimPluginInstallerReadJson($manifestPath);
    if (!is_array($manifest)) {
        chimPluginInstallerRemoveDirectory($stagingDir);
        throw new Exception("Package did not contain a valid manifest.json at its root.");
    }

    $manifest["channel"] = $channel["id"];
    $manifest["channel_label"] = $channel["label"];
    if (!isset($manifest["git_repo"])) {
        $manifest["git_repo"] = $githubRepo;
    }
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    if (is_dir($targetDir)) {
        chimPluginInstallerRemoveDirectory($targetDir);
    }
    if (!rename($stagingDir, $targetDir)) {
        chimPluginInstallerRemoveDirectory($stagingDir);
        throw new Exception("Failed to move staged plugin into target directory.");
    }

    echo "<p class='log-info'>Checking for database migrations...</p>\n";
    if (!chimPluginInstallerRunMigrations($targetDir, $packageName)) {
        throw new Exception("Failed to run database migrations.");
    }

    chimPluginInstallerRunComposer($targetDir);
    echo "<p class='log-success'>Package successfully installed/updated.</p>\n";
    return true;
}

$pluginId = (string)($_GET["PLUGIN_ID"] ?? "");
$packageName = (string)($_GET["PACKAGE_NAME"] ?? "");
$githubRepo = (string)($_GET["GITHUB_REPO"] ?? "");
$requestedChannel = (string)($_GET["CHANNEL"] ?? "");
$forceInstall = isset($_GET["FORCE"]) && $_GET["FORCE"] !== "0";

$repositoryEntry = chimPluginInstallerFindRepositoryEntry($pluginRepository, $pluginId, $packageName, $githubRepo);
if (is_array($repositoryEntry)) {
    $packageName = $packageName !== "" ? $packageName : (string)($repositoryEntry["name"] ?? "");
    $githubRepo = $githubRepo !== "" ? $githubRepo : (string)($repositoryEntry["git_repo"] ?? "");
}

$errors = [];
if ($packageName === "" || !chimPluginInstallerEnsurePluginName($packageName)) {
    $errors[] = "Invalid or missing PACKAGE_NAME.";
}
if ($githubRepo === "" || !chimPluginInstallerEnsureGithubRepo($githubRepo)) {
    $errors[] = "Invalid or missing GITHUB_REPO.";
}

$targetDir = $enginePath . "ext" . DIRECTORY_SEPARATOR . $packageName;
$localManifest = chimPluginInstallerReadJson($targetDir . DIRECTORY_SEPARATOR . "manifest.json");
$channelSource = is_array($repositoryEntry) ? $repositoryEntry : (is_array($localManifest) ? $localManifest : []);
$channels = empty($errors) ? chimPluginInstallerNormalizeChannels($channelSource, $packageName, $githubRepo) : [];
$currentChannel = is_array($localManifest) ? (string)($localManifest["channel"] ?? "") : "";
$defaultChannel = (string)((is_array($repositoryEntry) ? ($repositoryEntry["default_channel"] ?? "") : "") ?: ($currentChannel ?: "main"));
$requestedChannel = $requestedChannel !== "" ? $requestedChannel : $defaultChannel;

if (empty($errors) && !isset($channels[$requestedChannel])) {
    $errors[] = "Unknown plugin channel: " . $requestedChannel;
}

$channel = empty($errors) ? $channels[$requestedChannel] : null;
$remoteManifest = $channel ? chimPluginInstallerGetRemoteManifest($channel, $githubRepo) : false;
$remoteVersion = is_array($remoteManifest) ? (string)($remoteManifest["version"] ?? "") : "";
if ($channel && $remoteVersion !== "") {
    $channel["package_urls"] = array_map(function ($url) use ($remoteVersion) {
        return strtr($url, ["<version>" => $remoteVersion]);
    }, $channel["package_urls"]);
}
$currentVersion = is_array($localManifest) ? (string)($localManifest["version"] ?? "") : "";
$installed = is_array($localManifest);
$channelChanged = $installed && $currentChannel !== "" && $currentChannel !== $requestedChannel;
$updateAvailable = !$installed || $channelChanged || $forceInstall;
if (!$updateAvailable && $remoteVersion !== "" && $currentVersion !== "") {
    $updateAvailable = version_compare($remoteVersion, $currentVersion, ">");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHIM Plugin Installer: <?php echo chimPluginInstallerEscape($packageName); ?></title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/chim-theme.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'chim-theme.css'); ?>">
    <style>
        body { padding: 20px; background-color: #2c2c2c; color: #f8f9fa; font-family: 'Futura CondensedLight', Arial, sans-serif; }
        .installer-container { max-width: 900px; margin: 40px auto; background-color: #1a1a1a; padding: 30px; border-radius: 8px; border: 1px solid #3a3a3a; }
        h1 { color: #fff; text-align: center; }
        h3 { color: rgb(242, 124, 17); margin-top: 30px; border-bottom: 1px solid #3a3a3a; padding-bottom: 8px; }
        .version-info-block { background-color: #2d2d2d; padding: 20px; border-radius: 6px; margin-bottom: 30px; border: 1px solid #4a4a4a; }
        .installer-log { background-color: #111; color: #ccc; padding: 20px; border-radius: 6px; font-family: 'Spline Sans Mono', monospace; font-size: 14px; white-space: pre-wrap; word-wrap: break-word; max-height: 450px; overflow-y: auto; border: 1px solid #333; margin-top: 15px; }
        .installer-log p { margin: 6px 0; padding: 3px 0; line-height: 1.5; }
        .log-info { color: #5bc0de; }
        .log-action, .log-running { color: #f0ad4e; }
        .log-completed, .log-success { color: #28a745; }
        .log-skipped { color: #888; }
        .log-error, .log-failed { color: #d9534f; font-weight: bold; }
        .system-command-output { border-left: 3px solid #444; padding-left: 10px; margin: 8px 0 12px 15px; font-size: 0.85em; color: #aaa; }
        .status-message { padding: 15px 20px; margin-top: 25px; border-radius: 6px; font-weight: bold; text-align: center; }
        .status-success { background-color: #28a745; color: white; border: 1px solid #1e7e34; }
        .status-error { background-color: #d9534f; color: white; border: 1px solid #c9302c; }
    </style>
</head>
<body>
    <div class="installer-container">
        <h1>CHIM Plugin Installer</h1>
        <a href="core/config_hub.php?tab=serverplugins" class="button btn-primary">&laquo; Back to Plugin Manager</a>

        <div class="version-info-block">
            <h3>Version Information</h3>
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <p class="log-error"><?php echo chimPluginInstallerEscape($error); ?></p>
                <?php endforeach; ?>
            <?php else: ?>
                <p><strong>Package:</strong> <?php echo chimPluginInstallerEscape($packageName); ?></p>
                <p><strong>GitHub Repo:</strong> <?php echo chimPluginInstallerEscape($githubRepo); ?></p>
                <p><strong>Selected Channel:</strong> <?php echo chimPluginInstallerEscape($channel["label"]); ?> <span style="color:#aaa;">(<?php echo chimPluginInstallerEscape($channel["id"]); ?>)</span></p>
                <?php if ($installed): ?>
                    <p><strong>Current Version:</strong> <?php echo chimPluginInstallerEscape($currentVersion); ?></p>
                    <p><strong>Current Channel:</strong> <?php echo chimPluginInstallerEscape($currentChannel ?: "legacy"); ?></p>
                <?php endif; ?>
                <?php if ($remoteVersion !== ""): ?>
                    <p><strong>Remote Version:</strong> <?php echo chimPluginInstallerEscape($remoteVersion); ?></p>
                <?php else: ?>
                    <p class="log-error">Could not retrieve remote version information for this channel.</p>
                <?php endif; ?>
                <p><strong>Install Needed:</strong> <?php echo $updateAvailable ? "Yes" : "No"; ?></p>
            <?php endif; ?>
        </div>

        <?php
        if (!empty($errors)) {
            echo '<div class="status-message status-error">Could not proceed due to installer configuration errors.</div>';
        } elseif ($updateAvailable) {
            echo '<h3>Installation Log</h3>';
            echo '<div class="installer-log">';
            try {
                chimPluginInstallerInstallPackage($channel, $targetDir, $packageName, $githubRepo);
                echo '</div>';
                echo '<div class="status-message status-success">Installation/update process completed.</div>';
            } catch (Exception $e) {
                echo '<p class="log-error">Error: ' . chimPluginInstallerEscape($e->getMessage()) . '</p>';
                echo '</div>';
                echo '<div class="status-message status-error">Installation/update process failed.</div>';
            }
        } else {
            echo '<div class="status-message status-success">Plugin is already installed and up to date for this channel.</div>';
        }
        ?>
    </div>
</body>
</html>
