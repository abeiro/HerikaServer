<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
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
/* Centered orange header like Oghma page */
.page-header { text-align:center; margin: 0 0 20px 0; padding: 20px; background:#2a2a2a; border-radius:8px; border:1px solid #4a4a4a; }
.page-header h1, #page-title, #title-text { font-family:'MagicCards', serif !important; }
.page-header h1 { margin:0; word-spacing:8px; font-size:2.2em; color: rgb(242, 124, 17); text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
.table-container { background-color: #2a2a2a; border-radius: 5px; padding: 15px; margin-bottom: 20px; overflow-x: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; background-color: #3a3a3a; margin-bottom: 20px; font-size: small; }
th { background-color: #1a1a1a; color: #fff; font-weight: bold; padding: 12px; text-align: left; border-bottom: 2px solid #444; }
td { padding: 10px; text-align: left; border-bottom: 1px solid #444; color: #f8f9fa; }
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
                <th>Latest Version</th>
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
                $latestVersion = '';
                if (!empty($gitRepo)) {
                    $latestVersion = getLatestGithubRelease($gitRepo);
                }

                echo '<tr>';
                echo '<td>' . htmlspecialchars($name) . '</td>';
                echo '<td>' . htmlspecialchars($description) . '</td>';
                echo '<td>' . htmlspecialchars($version) . '</td>';
                if (!empty($latestVersion) && !empty($version) && version_compare($latestVersion, $version, '>')) {
                    echo '<td style="color: #ff4444; font-weight: bold;">' . htmlspecialchars($latestVersion) . ' <span title="Update Available">⬆️</span></td>';
                } else {
                    echo '<td>' . htmlspecialchars($latestVersion) . '</td>';
                }
                echo '<td>';
                if (!empty($configUrl)) {
                    echo '<button onclick="window.open(\'' . htmlspecialchars($configUrl) . '\', \'_blank\')" class="btn-base btn-primary">Plugin Page</button>';
                    if (isset($manifest['schema_version']) && $manifest['schema_version']==2) {
                        echo ' <button onclick="window.open(\'' . htmlspecialchars("/HerikaServer/ext/generic_installer.php?PACKAGE_NAME={$manifest['name']}&GITHUB_REPO={$manifest['git_repo']}") . '\', \'_blank\')" class="btn-base btn-save">Update plugin</button>';
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
        echo '<div style="display:flex; align-items:center; margin-top: 20px;">';
        echo '<h1 style="margin-right: 10px;">CHIM Plugins Repository</h1>';
        echo '</div>';
        echo '<p>Here you can download extensions that add extra AI features to CHIM.</p>';

        // Load plugin repository data from JSON file
        $pluginRepositoryFile = __DIR__ . '/data/plugin_repository.json';
        $pluginRepository = [];
        if (file_exists($pluginRepositoryFile)) {
            $jsonData = json_decode(file_get_contents($pluginRepositoryFile), true);
            if ($jsonData && isset($jsonData['plugins'])) {
                $pluginRepository = $jsonData['plugins'];
            }
        }

        echo '<table border="1">';
        echo '<tr>
                <th>Plugin</th>
                <th>Description</th>
                <th>Plugin Menu</th>
            </tr>';
        foreach ($pluginRepository as $plugin) {
            $name = $plugin['name'];
            $description = $plugin['description'] ?? 'No description available';
            $configUrl = "/HerikaServer/ext/generic_installer.php?PACKAGE_NAME={$plugin['name']}&GITHUB_REPO={$plugin['git_repo']}";
            $githubUrl = $plugin['github_url'] ?? '';
            $modDownloadUrl = $plugin['mod_download_url'] ?? '';
            $isInstalled = in_array($name, $installed_plugins);

            echo '<tr>';
            echo '<td>' . htmlspecialchars($name) . '</td>';
            echo '<td>' . htmlspecialchars($description) . '</td>';
            echo '<td>';
            if ($isInstalled) {
                echo '<button class="btn-base" disabled style="opacity: 0.6;">Already Installed</button>';
            } else {
                echo '<button onclick="window.open(\'' . htmlspecialchars($configUrl) . '\', \'_blank\')" class="btn-base btn-save">Install Plugin</button>';
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


