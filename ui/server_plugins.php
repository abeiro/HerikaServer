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
main { padding-top: <?php echo $isEmbedded ? '40' : '80'; ?>px; padding-bottom: 40px; padding-left: 10px; }
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
/* Centered orange header */
.page-header { 
    text-align: center; 
    margin: 0 0 20px 0; 
    padding: 20px; 
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border-radius: 10px; 
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
}
.page-header h1, #page-title, #title-text { font-family:'MagicCards', serif !important; }
.page-header h1 { 
    margin: 0 0 8px 0; 
    word-spacing: 8px; 
    font-size: 2.2em; 
    color: rgb(242, 124, 17); 
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5); 
}
.page-subtitle {
    margin: 0;
    color: #bbb;
    font-size: 1.1em;
    line-height: 1.6;
}
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
</style>

<main>
    <div class="page-header">
        <h1 id="page-title"><span id="title-text">Server Plugins</span></h1>
        <p class="page-subtitle">Manage and install plugins to extend CHIM functionality</p>
    </div>

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

        if (isset($_POST['download_minai'])) {
            $zipUrl = 'https://github.com/MinLL/MinAI/archive/refs/heads/stable.zip';
            $zipFile = tempnam(sys_get_temp_dir(), 'minai_') . '.zip';
            $zipContent = @file_get_contents($zipUrl);
            if ($zipContent !== false) {
                file_put_contents($zipFile, $zipContent);
                $zip = new ZipArchive;
                if ($zip->open($zipFile) === TRUE) {
                    $destination = __DIR__ . '/../ext/';
                    $zip->extractTo($destination);
                    $zip->close();
                    @unlink($zipFile);
                    // Move extracted folder
                    $sourcePath = $destination . 'MinAI-stable/minai_plugin';
                    $targetPath = $destination . 'minai_plugin';
                    if (is_dir($sourcePath)) {
                        if (is_dir($targetPath)) rrmdir($targetPath);
                        @rename($sourcePath, $targetPath);
                        rrmdir($destination . 'MinAI-stable');
                    }
                } else {
                    @unlink($zipFile);
                }
            }
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

                echo '<tr>';
                echo '<td>' . htmlspecialchars($name) . '</td>';
                echo '<td>' . htmlspecialchars($description) . '</td>';
                echo '<td>' . htmlspecialchars($version) . '</td>';
                echo '<td>' . htmlspecialchars($currentChannel['label'] ?? $currentChannelId) . '</td>';
                if (!empty($latestVersion) && !empty($version) && version_compare($latestVersion, $version, '>')) {
                    echo '<td style="color: #ff4444; font-weight: bold;">' . htmlspecialchars($latestVersion) . ' <span title="Update Available">⬆️</span></td>';
                } else {
                    echo '<td>' . htmlspecialchars($latestVersion) . '</td>';
                }
                echo '<td>';
                if (!empty($configUrl)) {
                    echo '<button onclick="window.open(\'' . htmlspecialchars($configUrl) . '\', \'_blank\')" class="btn-base btn-primary">Plugin Page</button>';
                    if (isset($manifest['schema_version']) && $manifest['schema_version']==2 && !empty($gitRepo)) {
                        $forceCurrentChannel = !empty($currentChannel['allow_force']);
                        $updateUrl = buildPluginInstallerUrl($pluginId, $name, $gitRepo, $currentChannelId, $forceCurrentChannel);
                        echo ' <button onclick="window.open(\'' . htmlspecialchars($updateUrl) . '\', \'_blank\')" class="btn-base btn-save">Update ' . htmlspecialchars($currentChannel['label'] ?? 'Plugin') . '</button>';
                        foreach ($channels as $channelId => $channel) {
                            if ($channelId === $currentChannelId) {
                                continue;
                            }
                            $switchUrl = buildPluginInstallerUrl($pluginId, $name, $gitRepo, $channelId, true);
                            echo ' <button onclick="window.open(\'' . htmlspecialchars($switchUrl) . '\', \'_blank\')" class="btn-base btn-primary">Switch to ' . htmlspecialchars($channel['label']) . '</button>';
                        }
                    }
                    if (!empty($modDownloadUrl)) {
                        echo ' <button onclick="window.open(\'' . htmlspecialchars($modDownloadUrl) . '\', \'_blank\')" class="btn-base btn-save">Skyrim MOD</button>';
                    }

                } else {
                    echo 'No Plugin Page';
                }
                echo '</td>';
                echo '<td>';
                if ($folder !== 'herika_heal' && $folder !== 'time_awareness') {
                    echo '<form method="post" style="margin:0;" onsubmit="return confirm(\'Are you sure you want to delete the ' . htmlspecialchars($name) . ' plugin?\');">';
                    echo '<input type="hidden" name="delete_plugin" value="' . htmlspecialchars($folder) . '">';
                    echo '<button type="submit" class="btn-base btn-danger">Delete Plugin</button>';
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
        echo '<p style="text-align: center; color: #bbb; margin: 0 0 20px 0;">Download extensions that add extra AI features to CHIM</p>';

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

            echo '<tr>';
            echo '<td>' . htmlspecialchars($name) . '</td>';
            echo '<td>' . htmlspecialchars($description) . '</td>';
            echo '<td>';
            if ($isInstalled) {
                echo '<button class="btn-base" disabled style="opacity: 0.6;">Already Installed</button>';
            } else {
                foreach ($channels as $channelId => $channel) {
                    $installUrl = buildPluginInstallerUrl($pluginId, $name, $plugin['git_repo'], $channelId, false);
                    echo ' <button onclick="window.open(\'' . htmlspecialchars($installUrl) . '\', \'_blank\')" class="btn-base btn-save">Install ' . htmlspecialchars($channel['label']) . '</button>';
                }
            }
            if (!empty($githubUrl)) {
                echo ' <button onclick="window.open(\'' . htmlspecialchars($githubUrl) . '\', \'_blank\')" class="btn-base btn-primary">GitHub</button>';
            }
            if (!empty($modDownloadUrl)) {
                echo ' <button onclick="window.open(\'' . htmlspecialchars($modDownloadUrl) . '\', \'_blank\')" class="btn-base btn-primary">Mod Download</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // MinAI plugin quick-download
        $pluginFoldersRoot = __DIR__ . '/../ext/';
        $minaiInstalled = is_dir($pluginFoldersRoot . 'minai_plugin');
        echo '<table border="1">';
        echo '<tr><th>Plugin</th><th>Description</th><th>Mod Page</th><th>Skyrim Mod Download</th></tr>';
        echo '<tr>';
        echo '<td style="text-align:center;">';
        if ($minaiInstalled) {
            echo '<button class="btn-base btn-primary" disabled>MinAI Installed</button>';
        } else {
            echo '<form method="post" style="margin:0;"><input type="hidden" name="download_minai" value="1"><button type="submit" class="btn-base btn-primary">Download MinAI</button></form>';
        }
        echo '</td>';
        echo '<td>Extension for CHIM that expands its capabilities and optionally adds NSFW integrations.<br><span style="color: #ff6b6b; font-size: 0.9em;"><strong>Note:</strong> No longer supported by the original author. May have compatibility issues. Use at your own risk.</span></td>';
        echo '<td><button onclick="window.open(\'https://github.com/MinLL/MinAI\', \'_blank\')" class="btn-base btn-primary">More Info</button></td>';
        echo '<td><button onclick="window.open(\'https://github.com/MinLL/MinAI/releases\', \'_blank\')" class="btn-base btn-primary">Mod Download</button></td>';
        echo '</tr></table>';
        echo '</div>'; // Close the second table-container
        ?>
    </div>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


