<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

// Determine web root (match other pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$TITLE = "CHIM - Server Plugins";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");

$isEmbedded = (isset($_GET['embed']) && $_GET['embed']);
if (!$isEmbedded) {
    include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding: <?php echo $isEmbedded ? '10px' : '80px'; ?> 10px 24px; }
footer { display: <?php echo $isEmbedded? 'none' : 'block'; ?>; }
/* MagicCards font import and heading styling to match core pages */
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}
h1 { font-family: 'MagicCards', serif; letter-spacing: 1.5px; }
/* Page header is the shared compact inline row (.chim-page-head in chim-theme.css). */
.page-header h1, #page-title, #title-text { font-family:'MagicCards', serif !important; }
.table-container { 
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-radius: 10px; 
    padding: 15px; 
    margin-bottom: 20px; 
    overflow-x: auto; 
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    border: 1px solid #3a3a3a;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.table-container:hover {
    border-color: rgba(242, 124, 17, 0.3);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.05);
}
table { 
    width: 100%; 
    border-collapse: collapse; 
    background: linear-gradient(135deg, rgba(58, 58, 58, 0.5), rgba(48, 48, 48, 0.6));
    margin-bottom: 20px; 
    font-size: small;
    border-radius: 8px;
    overflow: hidden;
}
th { 
    background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
    color: rgb(242, 124, 17); 
    font-weight: bold; 
    padding: 12px; 
    text-align: left; 
    border-bottom: 2px solid rgba(242, 124, 17, 0.3);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
td { 
    padding: 10px; 
    text-align: left; 
    border-bottom: 1px solid #3a3a3a; 
    color: #f8f9fa;
}
tr:hover td {
    background: rgba(242, 124, 17, 0.05);
}
/* Use main.css button system; do not override rounded corners/colors here */
/* .btn-* styles intentionally inherited from main.css */
.btn-base:disabled { opacity: 0.6; cursor: not-allowed; }
/* Extra styling parity with index.php */
.title-with-button { display:flex; align-items:center; }
.title-with-button h2 { margin-right:10px; margin-bottom:0; }
/* Featured-plugin branding (SHARMAT) - matches the SHARMAT UI language exactly:
   gold-glow name = .gold-glow-text (#FDF5D0 + neonPulse purple halo),
   buttons = .create-style-btn (dark indigo #252233, cream border, creamPulse breathing),
   row = batchCardBreathing purple over the dark indigo wash. */
@keyframes sharmatNeonPulse {
    from { text-shadow: none; }
    to {
        text-shadow:
            0 0 8px #C9A0DC,
            0 0 25px rgba(180, 130, 200, 0.8),
            0 0 50px rgba(150, 100, 180, 0.6),
            0 0 70px rgba(107, 91, 122, 0.4);
    }
}
@keyframes sharmatCreamPulse {
    from {
        text-shadow: 0 0 3px rgba(253, 245, 208, 0.2);
        box-shadow: 0 0 5px rgba(253, 245, 208, 0.2);
        border-color: rgba(253, 245, 208, 0.7);
    }
    to {
        text-shadow: 0 0 8px rgba(253, 245, 208, 0.6), 0 0 15px rgba(253, 245, 208, 0.4);
        box-shadow: 0 0 12px rgba(253, 245, 208, 0.5), 0 0 20px rgba(253, 245, 208, 0.3);
        border-color: #FDF5D0;
    }
}
/* All SHARMAT breathing runs on the SAME clock: 3s ease-in-out alternate, from = rest, to = peak,
   so the lady, the SHARMAT wording, the row, and the buttons inhale and exhale together. */
@keyframes sharmatRowBreathing {
    from {
        border-color: rgba(139, 92, 246, 0.4);
        box-shadow: inset 0 0 20px rgba(139, 92, 246, 0.05);
    }
    to {
        border-color: rgba(168, 85, 247, 0.7);
        box-shadow: inset 0 0 30px rgba(168, 85, 247, 0.1);
    }
}
@keyframes sharmatIconPulse {
    from { filter: drop-shadow(0 0 5px rgba(168, 85, 247, 0.7)); }
    to { filter: drop-shadow(0 0 12px rgba(168, 85, 247, 1)) drop-shadow(0 0 18px rgba(253, 245, 208, 0.45)); }
}
.featured-plugin-cell { display: inline-flex; align-items: center; gap: 12px; }
.featured-plugin-icon { width: 52px; height: 52px; object-fit: contain; animation: sharmatIconPulse 3s ease-in-out infinite alternate; }
.featured-plugin-name {
    font-family: 'MagicCards', 'Segoe UI', sans-serif;
    font-weight: 600;
    font-size: 1.6em;
    letter-spacing: 1px;
    word-spacing: 6px;
    color: #FDF5D0;
    animation: sharmatNeonPulse 3s ease-in-out infinite alternate;
}
.btn-sharmat {
    font-family: 'MagicCards', 'Segoe UI', sans-serif;
    font-size: 14px;
    letter-spacing: 1px;
    word-spacing: 6px;
    color: #FDF5D0 !important;
    background: #252233 !important;
    border: 2px solid #FDF5D0 !important;
    border-radius: 10px;
    padding: 6px 14px;
    cursor: pointer;
    animation: sharmatCreamPulse 3s ease-in-out infinite alternate;
}
.btn-sharmat:hover { background: #2A2740 !important; }
.btn-sharmat:disabled { opacity: 0.75; }
/* SHARMAT delete button: maroon-red body, GOLD text + gold trim, breathing the SAME
   sharmatCreamPulse gold glow as the strip buttons so it's locked to their cadence. */
.btn-sharmat-danger {
    font-family: 'MagicCards', 'Segoe UI', sans-serif;
    font-size: 14px;
    letter-spacing: 1px;
    word-spacing: 6px;
    font-weight: 600;
    color: #FDF5D0 !important;
    background: linear-gradient(135deg, #3E0E22 0%, #2A0816 100%) !important;
    border: 2px solid #FDF5D0 !important;
    border-radius: 10px;
    padding: 6px 14px;
    cursor: pointer;
    animation: sharmatCreamPulse 3s ease-in-out infinite alternate;
}
.btn-sharmat-danger:hover {
    background: linear-gradient(135deg, #4E1230 0%, #360A1E 100%) !important;
}
/* The SHARMAT row itself: dark indigo wash + breathing purple separators instead of the gray line.
   Row text = the SHARMAT UI's regular note font in the lavender wording color, no glow.
   The channel label ("Live") keeps MagicCards + the gold glow via .featured-live. */
tr.featured-plugin-row td {
    background: linear-gradient(135deg, rgba(28, 26, 36, 0.9), rgba(37, 34, 51, 0.95)) !important;
    border-top: 1px solid rgba(139, 92, 246, 0.5);
    border-bottom: 1px solid rgba(139, 92, 246, 0.5) !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-weight: normal;
    font-size: 1em;
    line-height: 1.6;
    color: #C9A8FF;
    animation: sharmatRowBreathing 3s ease-in-out infinite alternate;
}
.featured-live {
    font-family: 'MagicCards', 'Segoe UI', sans-serif;
    font-weight: 600;
    font-size: 1.15em;
    letter-spacing: 1px;
    word-spacing: 6px;
    color: #FDF5D0;
    animation: sharmatNeonPulse 3s ease-in-out infinite alternate;
}
.package-sync-card {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) auto;
    gap: 10px 14px;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    background: #242424;
}
.package-sync-card h2 { margin: 0 0 3px; color: #f27c11; font-size: 1.2em; }
.package-sync-card p { margin: 0; color: #bbb; font-size: 0.9em; }
.package-sync-status { grid-column: 1 / -1; padding: 8px 10px; border-radius: 5px; background: #181818; color: #ddd; }
.package-sync-list { display: grid; gap: 6px; margin-top: 8px; }
.package-sync-row { display: flex; justify-content: space-between; gap: 16px; padding: 7px 9px; background: #202020; border: 1px solid #363636; border-radius: 4px; }
.package-sync-version { color: #a9e7b7; white-space: nowrap; }
@media (max-width: 900px) {
    .package-sync-card { grid-template-columns: 1fr; }
}
</style>

<main>
    <div class="page-header chim-page-head">
        <h1 id="page-title" class="chim-page-head-title"><span id="title-text">Server Plugins</span></h1>
        <p class="page-subtitle chim-page-head-note">Manage and install plugins to extend CHIM functionality</p>
    </div>

    <section class="package-sync-card">
        <div>
            <h2>Automatic Game Plugin Sync</h2>
            <p>CHIM transfers bundled server plugins automatically when a save is loaded. No manual upload is required.</p>
        </div>
        <button id="package-sync-refresh" type="button" class="btn-base btn-primary">Refresh Status</button>
        <div id="package-sync-status" class="package-sync-status" role="status" aria-live="polite">Loading installed packages...</div>
    </section>

    <div class="table-container">
        <?php
        // Helpers
        function rrmdir($dir) {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != '.' && $object != '..') {
                        $path = $dir . DIRECTORY_SEPARATOR . $object;
                        if (is_dir($path)) rrmdir($path); else @unlink($path);
                    }
                }
                @rmdir($dir);
            }
        }

        // Get latest version from GitHub manifest.json (parity with index.php)
        function getLatestGithubRelease($repo) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/{$repo}/contents/manifest.json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'CHIM-Server');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
            $output = curl_exec($ch);
            curl_close($ch);
            if ($output) {
                $data = json_decode($output, true);
                if (isset($data['content'])) {
                    $manifestContent = base64_decode($data['content']);
                    $manifest = json_decode($manifestContent, true);
                    if ($manifest && isset($manifest['version'])) {
                        return $manifest['version'];
                    }
                }
            }
            return '';
        }

        function fetchPluginManagerUrl($url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'CHIM-Server');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json, application/json, */*']);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($output === false || $httpCode < 200 || $httpCode >= 300) {
                return false;
            }
            return $output;
        }

        function getPluginManagerManifestVersionFromUrl($url) {
            $output = fetchPluginManagerUrl($url);
            if (!$output) {
                return '';
            }
            $data = json_decode($output, true);
            if (!$data) {
                return '';
            }
            if (isset($data['content'])) {
                $manifestContent = base64_decode($data['content']);
                $manifest = json_decode($manifestContent, true);
            } else {
                $manifest = $data;
            }
            return ($manifest && isset($manifest['version'])) ? $manifest['version'] : '';
        }

        function normalizePluginManagerChannels($plugin, $packageName, $gitRepo) {
            $channels = [];
            $rawChannels = $plugin['channels'] ?? [];
            if (is_array($rawChannels) && !empty($rawChannels)) {
                foreach ($rawChannels as $channelId => $channelConfig) {
                    if (is_string($channelConfig)) {
                        $channelConfig = ['branch' => $channelConfig];
                    }
                    if (!is_array($channelConfig)) {
                        continue;
                    }
                    $branch = (string)($channelConfig['branch'] ?? $channelId);
                    $label = (string)($channelConfig['label'] ?? ucfirst((string)$channelId));
                    $manifestUrl = (string)($channelConfig['manifest_url'] ?? '');
                    if ($manifestUrl === '' && $branch !== '') {
                        $manifestUrl = "https://raw.githubusercontent.com/{$gitRepo}/{$branch}/manifest.json";
                    }
                    $manifestUrl = strtr($manifestUrl, [
                        '<package>' => $packageName,
                        '<repo>' => $gitRepo,
                        '<channel>' => (string)$channelId,
                        '<branch>' => $branch,
                    ]);
                    $channels[(string)$channelId] = [
                        'id' => (string)$channelId,
                        'label' => $label,
                        'branch' => $branch,
                        'manifest_url' => $manifestUrl,
                        'allow_force' => (bool)($channelConfig['allow_force'] ?? ($channelId !== 'main')),
                    ];
                }
            }

            if (empty($channels)) {
                $channels['main'] = [
                    'id' => 'main',
                    'label' => 'Live',
                    'branch' => '',
                    'manifest_url' => "https://api.github.com/repos/{$gitRepo}/contents/manifest.json",
                    'allow_force' => false,
                ];
            }
            return $channels;
        }

        function getPluginManagerChannelVersion($gitRepo, $channel) {
            if (!empty($channel['manifest_url'])) {
                return getPluginManagerManifestVersionFromUrl($channel['manifest_url']);
            }
            if (!empty($channel['branch'])) {
                return getPluginManagerManifestVersionFromUrl("https://api.github.com/repos/{$gitRepo}/contents/manifest.json?ref=" . rawurlencode($channel['branch']));
            }
            return getLatestGithubRelease($gitRepo);
        }

        function findPluginRepositoryEntry($pluginRepository, $manifest, $folder) {
            $manifestName = $manifest['name'] ?? $folder;
            $manifestRepo = $manifest['git_repo'] ?? '';
            foreach ($pluginRepository as $pluginId => $plugin) {
                if (!is_array($plugin)) {
                    continue;
                }
                if (($manifestRepo !== '' && ($plugin['git_repo'] ?? '') === $manifestRepo) || (($plugin['name'] ?? '') === $manifestName)) {
                    $plugin['_plugin_id'] = $pluginId;
                    return $plugin;
                }
            }
            return false;
        }

        function buildPluginInstallerUrl($pluginId, $packageName, $gitRepo, $channelId = 'main', $force = false) {
            $params = [
                'PACKAGE_NAME' => $packageName,
                'GITHUB_REPO' => $gitRepo,
                'CHANNEL' => $channelId,
            ];
            if ($pluginId !== '') {
                $params['PLUGIN_ID'] = $pluginId;
            }
            if ($force) {
                $params['FORCE'] = '1';
            }
            return 'server_plugin_installer.php?' . http_build_query($params);
        }

        // Load plugin repository data from JSON file
        $pluginRepositoryFile = __DIR__ . '/data/plugin_repository.json';
        $pluginRepository = [];
        if (file_exists($pluginRepositoryFile)) {
            $jsonData = json_decode(file_get_contents($pluginRepositoryFile), true);
            if ($jsonData && isset($jsonData['plugins'])) {
                $pluginRepository = $jsonData['plugins'];
            }
        }

        // Handle POST actions
        if (isset($_POST['delete_plugin'])) {
            $pluginToDelete = $_POST['delete_plugin'];
            $pluginPath = __DIR__ . '/../ext/' . $pluginToDelete;
            if (is_dir($pluginPath)) {
                rrmdir($pluginPath);
                $successMessage = "Plugin '" . htmlspecialchars($pluginToDelete) . "' has been deleted.";
            } else {
                $errorMessage = "Plugin '" . htmlspecialchars($pluginToDelete) . "' not found.";
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        if (isset($_POST['refresh_plugins'])) {
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // Gather installed plugins
        $pluginFoldersRoot = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "ext" . DIRECTORY_SEPARATOR;
        $pluginFolders = scandir($pluginFoldersRoot);
        foreach ($pluginFolders as $n => $folder) {
            if (!is_dir($pluginFoldersRoot . $folder) || substr($folder, 0, 1) === '.' || $folder === 'xLifeLink_plugin' || $folder === 'herika_heal' || $folder === 'time_awareness') {
                unset($pluginFolders[$n]);
            }
        }

        echo '<form method="post" style="margin:0 0 12px 0;">';
        echo '<button type="submit" name="refresh_plugins" value="1" class="btn-base btn-primary">Refresh Plugins</button>';
        echo '</form>';

        echo '<table border="1">';
        echo '<tr>
                <th>Plugin</th>
                <th>Description</th>
                <th>Current Version</th>
                <th>Channel</th>
                <th>Latest Channel Version</th>
                <th>Plugin Menu</th>
                <th>Delete Plugin</th>
            </tr>';

        $installed_plugins = [];

        foreach ($pluginFolders as $folder) {
            $manifestPath = $pluginFoldersRoot . $folder . '/manifest.json';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $name = $manifest['name'] ?? $folder;
                $description = $manifest['description'] ?? 'No description available';
                $configUrl = $manifest['config_url'] ?? '';
                $version = $manifest['version'] ?? '';
                $gitRepo = $manifest['git_repo'] ?? '';
                $modDownloadUrl = !empty($manifest['mod_download_url']) ? strtr($manifest['mod_download_url'],["<version>"=>"{$manifest['version']}"]) : '';
                $repositoryEntry = findPluginRepositoryEntry($pluginRepository, $manifest, $folder);
                $pluginId = is_array($repositoryEntry) ? (string)($repositoryEntry['_plugin_id'] ?? '') : '';
                $channelSource = is_array($repositoryEntry) ? $repositoryEntry : $manifest;
                $channels = !empty($gitRepo) ? normalizePluginManagerChannels($channelSource, $name, $gitRepo) : [];
                $currentChannelId = (string)($manifest['channel'] ?? ($channelSource['default_channel'] ?? 'main'));
                if (!isset($channels[$currentChannelId]) && !empty($channels)) {
                    $currentChannelId = array_key_first($channels);
                }
                $currentChannel = !empty($channels) ? $channels[$currentChannelId] : ['id' => $currentChannelId, 'label' => ($currentChannelId ?: 'legacy'), 'allow_force' => false];

                $latestVersion = '';
                if (!empty($gitRepo) && !empty($channels)) {
                    $latestVersion = getPluginManagerChannelVersion($gitRepo, $currentChannel);
                }

                // Featured-plugin branding (display_name / icon / featured from manifest or repository entry)
                $displayName = (string)($manifest['display_name'] ?? (is_array($repositoryEntry) ? ($repositoryEntry['display_name'] ?? $name) : $name));
                $isFeatured = !empty($manifest['featured']) || (is_array($repositoryEntry) && !empty($repositoryEntry['featured']));
                $iconRef = (string)($manifest['icon'] ?? (is_array($repositoryEntry) ? ($repositoryEntry['icon'] ?? '') : ''));
                $iconUrl = '';
                if ($iconRef !== '') {
                    $iconUrl = preg_match('#^https?://#i', $iconRef) ? $iconRef : ($webRoot . '/ext/' . rawurlencode($folder) . '/' . ltrim($iconRef, '/'));
                }
                $rowBtnPrimary = $isFeatured ? 'btn-base btn-sharmat' : 'btn-base btn-primary';
                $rowBtnSave = $isFeatured ? 'btn-base btn-sharmat' : 'btn-base btn-save';

                echo $isFeatured ? '<tr class="featured-plugin-row">' : '<tr>';
                if ($isFeatured) {
                    echo '<td><span class="featured-plugin-cell">' . ($iconUrl !== '' ? '<img src="' . htmlspecialchars($iconUrl) . '" class="featured-plugin-icon" alt="">' : '') . '<span class="featured-plugin-name">' . htmlspecialchars($displayName) . '</span></span></td>';
                } else {
                    echo '<td>' . htmlspecialchars($displayName) . '</td>';
                }
                echo '<td>' . htmlspecialchars($description) . '</td>';
                echo '<td>' . htmlspecialchars($version) . '</td>';
                $channelLabelHtml = htmlspecialchars($currentChannel['label'] ?? $currentChannelId);
                echo '<td>' . ($isFeatured ? '<span class="featured-live">' . $channelLabelHtml . '</span>' : $channelLabelHtml) . '</td>';
                if (!empty($latestVersion) && !empty($version) && version_compare($latestVersion, $version, '>')) {
                    echo '<td style="color: #ff4444; font-weight: bold;">' . htmlspecialchars($latestVersion) . ' <span title="Update Available">⬆️</span></td>';
                } else {
                    echo '<td>' . htmlspecialchars($latestVersion) . '</td>';
                }
                echo '<td>';
                if (!empty($configUrl)) {
                    echo '<button onclick="window.open(\'' . htmlspecialchars($configUrl) . '\', \'_blank\')" class="' . $rowBtnPrimary . '">Plugin Page</button>';
                    if (isset($manifest['schema_version']) && $manifest['schema_version']==2 && !empty($gitRepo)) {
                        $forceCurrentChannel = !empty($currentChannel['allow_force']);
                        $updateUrl = buildPluginInstallerUrl($pluginId, $name, $gitRepo, $currentChannelId, $forceCurrentChannel);
                        echo ' <button onclick="window.open(\'' . htmlspecialchars($updateUrl) . '\', \'_blank\')" class="' . $rowBtnSave . '">Update ' . htmlspecialchars($currentChannel['label'] ?? 'Plugin') . '</button>';
                        foreach ($channels as $channelId => $channel) {
                            if ($channelId === $currentChannelId) {
                                continue;
                            }
                            $switchUrl = buildPluginInstallerUrl($pluginId, $name, $gitRepo, $channelId, true);
                            echo ' <button onclick="window.open(\'' . htmlspecialchars($switchUrl) . '\', \'_blank\')" class="' . $rowBtnPrimary . '">Switch to ' . htmlspecialchars($channel['label']) . '</button>';
                        }
                    }
                    if (!empty($modDownloadUrl)) {
                        echo ' <button onclick="window.open(\'' . htmlspecialchars($modDownloadUrl) . '\', \'_blank\')" class="' . $rowBtnSave . '">Download Skyrim Modfile</button>';
                    }

                } else {
                    echo 'No Plugin Page';
                }
                echo '</td>';
                echo '<td>';
                if ($folder !== 'herika_heal' && $folder !== 'time_awareness') {
                    echo '<form method="post" style="margin:0;" onsubmit="return confirm(\'Are you sure you want to delete the ' . htmlspecialchars($name) . ' plugin?\');">';
                    echo '<input type="hidden" name="delete_plugin" value="' . htmlspecialchars($folder) . '">';
                    echo '<button type="submit" class="btn-base ' . ($isFeatured ? 'btn-sharmat-danger' : 'btn-danger') . '">Delete Plugin</button>';
                    echo '</form>';
                } else {
                    echo 'Cannot be deleted';
                }
                echo '</td>';
                echo '</tr>';

                $installed_plugins[] = $name;
            }
        }
        echo '</table>';

        echo '<br>';
        echo '<div class="table-container" style="margin-top: 30px;">';
        echo '<h1 style="margin: 0 0 15px 0; text-align: center; color: rgb(242, 124, 17); font-family: \'MagicCards\', serif; font-size: 1.8em;">CHIM Plugins Repository</h1>';
        echo '<p style="text-align: center; color: #bbb; margin: 0 0 6px 0;">Download extensions that add extra AI features to CHIM</p>';
        echo '<p style="text-align: center; color: #bbb; margin: 0 0 20px 0;">Built a plugin of your own? See <a href="https://dwemerdynamics.com/chim/modders-guide.html#SubmittingPluginToRepository" target="_blank" rel="noopener noreferrer">how to get your plugin listed here</a>.</p>';

        echo '<table border="1">';
        echo '<tr>
                <th>Plugin</th>
                <th>Description</th>
                <th>Plugin Menu</th>
            </tr>';
        foreach ($pluginRepository as $pluginId => $plugin) {
            $name = $plugin['name'];
            $description = $plugin['description'] ?? 'No description available';
            $githubUrl = $plugin['github_url'] ?? '';
            $modDownloadUrl = $plugin['mod_download_url'] ?? '';
            $isInstalled = in_array($name, $installed_plugins);
            $channels = normalizePluginManagerChannels($plugin, $name, $plugin['git_repo']);
            if (!empty($modDownloadUrl) && strpos($modDownloadUrl, '<version>') !== false) {
                $defaultChannelId = (string)($plugin['default_channel'] ?? 'main');
                $versionChannel = $channels[$defaultChannelId] ?? reset($channels);
                $downloadVersion = getPluginManagerChannelVersion($plugin['git_repo'], $versionChannel);
                if ($downloadVersion !== '') {
                    $modDownloadUrl = strtr($modDownloadUrl, ['<version>' => $downloadVersion]);
                }
            }

            // Featured-plugin branding (SHARMAT)
            $displayName = (string)($plugin['display_name'] ?? $name);
            $isFeatured = !empty($plugin['featured']);
            $repoIconUrl = (string)($plugin['icon'] ?? '');
            $rowBtnPrimary = $isFeatured ? 'btn-base btn-sharmat' : 'btn-base btn-primary';
            $rowBtnSave = $isFeatured ? 'btn-base btn-sharmat' : 'btn-base btn-save';

            echo $isFeatured ? '<tr class="featured-plugin-row">' : '<tr>';
            if ($isFeatured) {
                echo '<td><span class="featured-plugin-cell">' . ($repoIconUrl !== '' ? '<img src="' . htmlspecialchars($repoIconUrl) . '" class="featured-plugin-icon" alt="">' : '') . '<span class="featured-plugin-name">' . htmlspecialchars($displayName) . '</span></span></td>';
            } else {
                echo '<td>' . htmlspecialchars($displayName) . '</td>';
            }
            echo '<td>' . htmlspecialchars($description) . '</td>';
            echo '<td>';
            if ($isInstalled) {
                echo '<button class="btn-base' . ($isFeatured ? ' btn-sharmat' : '') . '" disabled style="opacity: 0.6;">Already Installed</button>';
            } else {
                $defaultChannelId = (string)($plugin['default_channel'] ?? 'main');
                foreach ($channels as $channelId => $channel) {
                    $installUrl = buildPluginInstallerUrl($pluginId, $name, $plugin['git_repo'], $channelId, false);
                    $installBase = $isFeatured ? 'Install ' . $displayName : 'Install Plugin';
                    $installLabel = ($channelId === $defaultChannelId) ? $installBase : 'Install ' . ($channel['label'] ?? ucfirst((string)$channelId));
                    echo ' <button onclick="window.open(\'' . htmlspecialchars($installUrl) . '\', \'_blank\')" class="' . $rowBtnSave . '">' . htmlspecialchars($installLabel) . '</button>';
                }
            }
            if (!empty($githubUrl)) {
                echo ' <button onclick="window.open(\'' . htmlspecialchars($githubUrl) . '\', \'_blank\')" class="' . $rowBtnPrimary . '">GitHub</button>';
            }
            if (!empty($modDownloadUrl)) {
                echo ' <button onclick="window.open(\'' . htmlspecialchars($modDownloadUrl) . '\', \'_blank\')" class="' . $rowBtnPrimary . '">Mod Download</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '</div>'; // Close the second table-container
        ?>
    </div>
</main>

<script>
(() => {
    const refresh = document.getElementById('package-sync-refresh');
    const status = document.getElementById('package-sync-status');
    const endpoint = <?php echo json_encode($webRoot . '/ui/api/plugin_packages.php'); ?>;

    const loadPackages = async () => {
        refresh.disabled = true;
        status.textContent = 'Loading installed packages...';
        try {
            const response = await fetch(`${endpoint}?action=packages`, { cache: 'no-store' });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Could not read automatic package status.');
            if (!payload.packages.length) {
                status.textContent = 'No game-bundled server plugins have synchronized yet.';
            } else {
                status.innerHTML = '<strong>Installed from game:</strong><div class="package-sync-list"></div>';
                const list = status.querySelector('.package-sync-list');
                for (const plugin of payload.packages) {
                    const row = document.createElement('div');
                    row.className = 'package-sync-row';
                    const name = document.createElement('span');
                    name.textContent = plugin.name;
                    const version = document.createElement('span');
                    version.className = 'package-sync-version';
                    version.textContent = plugin.version;
                    row.append(name, version);
                    list.append(row);
                }
            }
        } catch (error) {
            status.textContent = error.message;
        } finally {
            refresh.disabled = false;
        }
    };
    refresh.addEventListener('click', loadPackages);
    loadPackages();
})();
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>

