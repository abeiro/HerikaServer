<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "action_catalog.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "core_profiles.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");

// Determine web root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$narrator = new Narrator();

$advancedPromptKeys = [
    'dialogue_line_inline_response_narrator',
    'inline_narration_prompt_narrator',
    'dialogue_line_inline_response_npc',
    'inline_narration_prompt_npc',
    'narrator_welcome_prompt',
    'player_speech_style_prompt',
    'random_narration_prompt',
    'narrator_bored_prompt',
    'quest_comment_prompt',
];

$saveSuccess = false;
$saveMessage = '';

function narratorManagementGetNarratorActions()
{
    if (!function_exists('herikaActionCatalogDbReady') || !herikaActionCatalogDbReady()) {
        return [];
    }

    $rows = $GLOBALS["db"]->fetchAll("
        SELECT
            v.code_name,
            v.action_name,
            v.description,
            v.is_activated
        FROM public.combined_core_action v
        WHERE v.available_to_narrator = TRUE
        ORDER BY LOWER(v.action_name), LOWER(v.code_name)
    ");

    return is_array($rows) ? $rows : [];
}

function narratorManagementBuildNarratorActionStats($rows)
{
    $total = is_array($rows) ? count($rows) : 0;
    $enabled = 0;

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (function_exists('herikaActionCatalogToBool') && herikaActionCatalogToBool($row['is_activated'] ?? false)) {
                $enabled++;
            }
        }
    }

    return [
        'total' => $total,
        'enabled' => $enabled,
        'disabled' => max(0, $total - $enabled),
    ];
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && isset($_POST['action'])
) {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'toggle_narrator_action') {
        header('Content-Type: application/json');

        $codeName = trim((string)($_POST['code_name'] ?? ''));
        $targetEnabled = in_array(strtolower(trim((string)($_POST['target_enabled'] ?? '0'))), ['1', 'true', 'yes', 'on', 't'], true);

        if ($codeName === '') {
            echo json_encode(['success' => false, 'message' => 'Missing action code name.']);
            exit;
        }

        if (!function_exists('herikaActionCatalogDbReady') || !herikaActionCatalogDbReady()) {
            echo json_encode(['success' => false, 'message' => 'Action catalog tables are not available yet.']);
            exit;
        }

        $row = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;
        if (!is_array($row) || !herikaActionCatalogToBool($row['available_to_narrator'] ?? false)) {
            echo json_encode(['success' => false, 'message' => 'That action is not available to the narrator.']);
            exit;
        }

        if (!herikaActionCatalogUpsertCustomToggle($codeName, $targetEnabled)) {
            echo json_encode(['success' => false, 'message' => 'Could not update narration action toggle.']);
            exit;
        }

        $updatedRow = function_exists('herikaGetActionCatalogRow') ? herikaGetActionCatalogRow($codeName) : null;
        $actionRows = narratorManagementGetNarratorActions();
        $stats = narratorManagementBuildNarratorActionStats($actionRows);

        echo json_encode([
            'success' => true,
            'message' => sprintf('%s is now %s.', $codeName, $targetEnabled ? 'enabled' : 'disabled'),
            'code_name' => $codeName,
            'enabled' => is_array($updatedRow) ? herikaActionCatalogToBool($updatedRow['is_activated'] ?? false) : $targetEnabled,
            'stats' => $stats,
        ]);
        exit;
    }

    $promptKey = trim((string)($_POST['prompt_key'] ?? ''));
    $isAllowedPromptKey = in_array($promptKey, $advancedPromptKeys, true);

    if ($action === 'update_narrator_prompt') {
        header('Content-Type: application/json');

        if (!$isAllowedPromptKey) {
            echo json_encode(['success' => false, 'message' => 'Invalid prompt key.']);
            exit;
        }

        $customPrompt = trim((string)($_POST['custom_prompt'] ?? ''));
        $setCustomPromptSql = $customPrompt === '' ? 'NULL' : $GLOBALS["db"]->escapeLiteral($customPrompt);
        $updatedAtSql = $GLOBALS["db"]->escapeLiteral(date('Y-m-d H:i:s'));
        $promptKeySql = $GLOBALS["db"]->escapeLiteral($promptKey);
        $result = $GLOBALS["db"]->execQuery(
            "UPDATE prompts
             SET custom_prompt = {$setCustomPromptSql},
                 updated_at = {$updatedAtSql}
             WHERE prompt_key = {$promptKeySql}"
        );
        echo json_encode([
            'success' => (bool)$result,
            'message' => $result ? 'Prompt saved successfully.' : 'Failed to save prompt.'
        ]);
        exit;
    }

    if ($action === 'clear_narrator_prompt') {
        header('Content-Type: application/json');

        if (!$isAllowedPromptKey) {
            echo json_encode(['success' => false, 'message' => 'Invalid prompt key.']);
            exit;
        }

        $updatedAtSql = $GLOBALS["db"]->escapeLiteral(date('Y-m-d H:i:s'));
        $promptKeySql = $GLOBALS["db"]->escapeLiteral($promptKey);
        $result = $GLOBALS["db"]->execQuery(
            "UPDATE prompts
             SET custom_prompt = NULL,
                 updated_at = {$updatedAtSql}
             WHERE prompt_key = {$promptKeySql}"
        );
        echo json_encode([
            'success' => (bool)$result,
            'message' => $result ? 'Custom prompt cleared.' : 'Failed to clear prompt.'
        ]);
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_narrator'])) {
    try {
        $roleplayName = Narrator::normalizeRoleplayName($_POST['roleplay_name'] ?? Narrator::DEFAULT_ROLEPLAY_NAME);
        $playerName = trim((string)($GLOBALS['PLAYER_NAME'] ?? ''));
        if ($playerName !== '' && strcasecmp($roleplayName, $playerName) === 0) {
            throw new InvalidArgumentException('Narrator roleplay name cannot match the player name.');
        }
        $escapedRoleplayName = $db->escape($roleplayName);
        $matchingNpc = $db->fetchOne(
            "SELECT npc_name FROM core_npc_master WHERE LOWER(npc_name) = LOWER('{$escapedRoleplayName}') LIMIT 1"
        );
        if ($matchingNpc) {
            throw new InvalidArgumentException('Narrator roleplay name cannot match an existing NPC name.');
        }
        $narrator->set('roleplay_name', $roleplayName);

        // Save boolean settings
        $narrator->set('enabled', isset($_POST['enabled']) && $_POST['enabled'] === '1' ? '1' : '0');
        $narrator->set('welcome_enabled', isset($_POST['welcome_enabled']) && $_POST['welcome_enabled'] === '1' ? '1' : '0');
        $narrator->set('random_enabled', isset($_POST['random_enabled']) && $_POST['random_enabled'] === '1' ? '1' : '0');
        $narrator->set('bored_enabled', isset($_POST['bored_enabled']) && $_POST['bored_enabled'] === '1' ? '1' : '0');
        $narrator->set('books_only_narrator', isset($_POST['books_only_narrator']) && $_POST['books_only_narrator'] === '1' ? '1' : '0');
        $narrator->set('hide_from_context', isset($_POST['hide_from_context']) && $_POST['hide_from_context'] === '1' ? '1' : '0');
        $inlineNarrationMode = isset($_POST['inline_narration_mode']) ? strtolower(trim((string)$_POST['inline_narration_mode'])) : 'disabled';
        if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
            $inlineNarrationMode = 'disabled';
        }
        $narrator->set('inline_narration_mode', $inlineNarrationMode);
        $narrator->set('preserve_asterisks_in_context', isset($_POST['preserve_asterisks_in_context']) && $_POST['preserve_asterisks_in_context'] === '1' ? '1' : '0');
        $narrator->set('remove_asterisks_from_player_input', isset($_POST['remove_asterisks_from_player_input']) && $_POST['remove_asterisks_from_player_input'] === '1' ? '1' : '0');
        $narrator->set('remove_asterisks_from_npc_output', isset($_POST['remove_asterisks_from_npc_output']) && $_POST['remove_asterisks_from_npc_output'] === '1' ? '1' : '0');
        $narrator->set('remove_player_autochat_asterisks', isset($_POST['remove_player_autochat_asterisks']) && $_POST['remove_player_autochat_asterisks'] === '1' ? '1' : '0');
        $narrator->set('diary_enabled', isset($_POST['diary_enabled']) && $_POST['diary_enabled'] === '1' ? '1' : '0');
        $narrator->set('auto_diary_enabled', isset($_POST['auto_diary_enabled']) && $_POST['auto_diary_enabled'] === '1' ? '1' : '0');
        $narrator->set('only_diary_access', isset($_POST['only_diary_access']) && $_POST['only_diary_access'] === '1' ? '1' : '0');
        
        // Save integer settings
        if (isset($_POST['random_chance'])) {
            $chance = intval($_POST['random_chance']);
            $chance = max(1, min(100, $chance)); // Clamp to 1-100
            $narrator->set('random_chance', (string)$chance);
        }
        if (isset($_POST['random_cooldown'])) {
            $cooldown = intval($_POST['random_cooldown']);
            $cooldown = max(0, min(10, $cooldown)); // Clamp to 0-10
            $narrator->set('random_cooldown', (string)$cooldown);
        }
        if (isset($_POST['bored_chance'])) {
            $boredChance = intval($_POST['bored_chance']);
            $boredChance = max(1, min(100, $boredChance));
            $narrator->set('bored_chance', (string)$boredChance);
        }
        if (isset($_POST['welcome_cooldown'])) {
            $cooldown = intval($_POST['welcome_cooldown']);
            $cooldown = max(1, min(1440, $cooldown)); // Clamp to 1-1440 (24 hours)
            $narrator->set('welcome_cooldown', (string)$cooldown);
        }
        if (isset($_POST['quest_comment_enabled'])) {
            $narrator->set('quest_comment_enabled', $_POST['quest_comment_enabled'] === '1' ? '1' : '0');
        }
        if (isset($_POST['quest_comment_chance'])) {
            $chance = intval($_POST['quest_comment_chance']);
            $chance = max(1, min(100, $chance)); // Clamp to 1-100
            $narrator->set('quest_comment_chance', (string)$chance);
        }
        if (isset($_POST['quest_comment_cooldown'])) {
            $cooldown = intval($_POST['quest_comment_cooldown']);
            $cooldown = max(1, min(60, $cooldown)); // Clamp to 1-60 minutes
            $narrator->set('quest_comment_cooldown', (string)$cooldown);
        }
        
        // Save dynamic profile settings
        $narrator->set('dynamic_profile', isset($_POST['dynamic_profile']) && $_POST['dynamic_profile'] === '1' ? '1' : '0');
        
        // Save dynamic profile fields array
        if (isset($_POST['dynamic_profile_fields']) && is_array($_POST['dynamic_profile_fields'])) {
            $fields = array_filter($_POST['dynamic_profile_fields'], function($v) {
                return in_array($v, ['personality', 'speechstyle', 'goals'], true);
            });
            $narrator->setDynamicProfileFields(array_values($fields));
        } else {
            $narrator->setDynamicProfileFields([]);
        }
        
        // Save profile_id
        if (isset($_POST['profile_id'])) {
            $profileId = intval($_POST['profile_id']);
            if ($profileId > 0) {
                $narrator->set('profile_id', (string)$profileId);
            }
        }
        
        // Save character fields
        if (isset($_POST['voiceid'])) {
            $narrator->set('voiceid', $_POST['voiceid']);
        }
        if (isset($_POST['core'])) {
            $narrator->set('core', $_POST['core']);
        }
        if (isset($_POST['background'])) {
            $narrator->set('background', $_POST['background']);
        }
        if (isset($_POST['personality'])) {
            $narrator->set('personality', $_POST['personality']);
        }
        if (isset($_POST['speechstyle'])) {
            $narrator->set('speechstyle', $_POST['speechstyle']);
        }
        if (isset($_POST['goals'])) {
            $narrator->set('goals', $_POST['goals']);
        }
        if (isset($_POST['oghma_knowledge'])) {
            $narrator->set('oghma_knowledge', $_POST['oghma_knowledge']);
        }
        if (isset($_POST['prompt_head'])) {
            $narrator->set('prompt_head', $_POST['prompt_head']);
        }
        
        $saveSuccess = true;
        $saveMessage = 'Narration settings saved successfully!';
    } catch (Exception $e) {
        $saveSuccess = false;
        $saveMessage = 'Error saving narration settings: ' . $e->getMessage();
    }
}

// Load all narrator settings
$allNarratorData = $narrator->getAll();

// Extract settings with defaults
$enabled = $narrator->getBool('enabled', true);
$welcomeEnabled = $narrator->getBool('welcome_enabled', false);
$randomEnabled = $narrator->getBool('random_enabled', false);
$randomChance = $narrator->getInt('random_chance', 15);
$randomCooldown = $narrator->getInt('random_cooldown', 2);
$boredEnabled = $narrator->getBool('bored_enabled', false);
$boredChance = $narrator->getInt('bored_chance', 25);
$welcomeCooldown = $narrator->getInt('welcome_cooldown', 10);
$questCommentEnabled = $narrator->getBool('quest_comment_enabled', false);
$questCommentChance = $narrator->getInt('quest_comment_chance', 10);
$questCommentCooldown = $narrator->getInt('quest_comment_cooldown', 3);
$booksOnlyNarrator = $narrator->getBool('books_only_narrator', false);
$hideFromContext = $narrator->getBool('hide_from_context', true);
$inlineNarrationMode = strtolower(trim((string)($narrator->get('inline_narration_mode') ?? '')));
if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
    if (isset($GLOBALS['INLINE_NARRATION_MODE']) && in_array(strtolower(trim((string)$GLOBALS['INLINE_NARRATION_MODE'])), ['disabled', 'narrator', 'npc', 'text_only'], true)) {
        $inlineNarrationMode = strtolower(trim((string)$GLOBALS['INLINE_NARRATION_MODE']));
    } else {
        $inlineNarrationMode = $narrator->getBool('inline_narration_enabled', isset($GLOBALS['INLINE_NARRATION_ENABLED']) ? (bool)$GLOBALS['INLINE_NARRATION_ENABLED'] : false) ? 'narrator' : 'disabled';
    }
}
$removePlayerAutochatAsterisks = $narrator->getBool(
    'remove_player_autochat_asterisks',
    isset($GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS']) ? (bool)$GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] : (isset($GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED']) ? !(bool)$GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'] : true)
);
$preserveAsterisksInContext = $narrator->getBool('preserve_asterisks_in_context', false);
$removeAsterisksFromPlayerInput = $narrator->getBool(
    'remove_asterisks_from_player_input',
    isset($GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] : (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] : true)
);
$removeAsterisksFromNpcOutput = $narrator->getBool(
    'remove_asterisks_from_npc_output',
    isset($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] : (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] : true)
);
$diaryEnabled = $narrator->getBool('diary_enabled', false);
$autoDiaryEnabled = $narrator->getBool('auto_diary_enabled', false);
$onlyDiaryAccess = $narrator->getBool('only_diary_access', false);
$dynamicProfileEnabled = $narrator->getBool('dynamic_profile', false);
$dynamicProfileFields = $narrator->getDynamicProfileFields();

// Extract character fields
$profileId = $narrator->getInt('profile_id', 1);
$roleplayName = $narrator->getRoleplayName();
$voiceid = $narrator->get('voiceid') ?? 'TheNarrator';
$core = $narrator->get('core') ?? '';
$background = $narrator->get('background') ?? '';
$personality = $narrator->get('personality') ?? '';
$speechstyle = $narrator->get('speechstyle') ?? '';
$goals = $narrator->get('goals') ?? '';
$oghmaKnowledge = $narrator->get('oghma_knowledge') ?? 'knowall';
$promptHead = $narrator->get('prompt_head') ?? '';

// Load profiles for dropdown
$profileMgr = new CoreProfile();
$allProfiles = $profileMgr->readAll();

// Load connector data for display
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");
$connectorMgr = new LLMConnector();
$allConnectors = $connectorMgr->readAll();
$ttsConnectorMgr = new TTSConnector();
$allTtsConnectors = $ttsConnectorMgr->readAll();

// Build lookup maps
$llmById = [];
foreach ($allConnectors as $conn) {
    $llmById[$conn['id']] = $conn['label'] ?? 'Connector ' . $conn['id'];
}
$ttsById = [];
foreach ($allTtsConnectors as $conn) {
    $ttsById[$conn['id']] = $conn['label'] ?? 'Connector ' . $conn['id'];
}

// Build profile connector map
$profilesConnById = [];
foreach ($allProfiles as $prof) {
    $profilesConnById[$prof['id']] = $prof;
}

// Get current profile data
$currentProfileData = $profilesConnById[$profileId] ?? null;

$advancedPromptOrderSql = [];
foreach ($advancedPromptKeys as $index => $advancedPromptKey) {
    $advancedPromptOrderSql[] = "WHEN " . $GLOBALS["db"]->escapeLiteral($advancedPromptKey) . " THEN " . ($index + 1);
}

$advancedPromptRows = $GLOBALS["db"]->fetchAll(
    "SELECT prompt_key, default_prompt, custom_prompt, description
     FROM prompts
     WHERE prompt_key IN (" . implode(', ', array_map([$GLOBALS["db"], 'escapeLiteral'], $advancedPromptKeys)) . ")
     ORDER BY CASE prompt_key " . implode(' ', $advancedPromptOrderSql) . " ELSE 999 END"
);
$advancedPrompts = is_array($advancedPromptRows) ? $advancedPromptRows : [];
$narratorActionRows = narratorManagementGetNarratorActions();
$narratorActionStats = narratorManagementBuildNarratorActionStats($narratorActionRows);

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$isEmbed) {
    require_once(__DIR__."/../profile_loader.php");
    $TITLE = "Narrator Management";
    ob_start();
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php if ($isEmbed): ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/chim-theme.css?v=<?php echo filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'chim-theme.css'); ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/player-narration.css?v=<?php echo filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'player-narration.css'); ?>">
<style>
    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Override main container styles */
    main {
        padding-top: <?php echo $isEmbed ? '20px' : '80px'; ?>;
        padding-bottom: 40px;
        padding-left: 5%;
        padding-right: 5%;
        /*width: 100%;*/
        margin: 0;
        display: flex;
        justify-content: center;
    }
    
    .page-container {
        width: 100%;
        max-width: 1200px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 28px;
        padding: 24px 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(28, 28, 28, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .page-header h1 {
        margin-bottom: 10px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header p {
        color: #aaa;
        font-size: 1em;
        margin: 0;
    }

    /* Content Layout */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 22px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.2s ease;
    }

    .content-section:hover {
        border-color: #4a4a4a;
    }

    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 18px;
        font-size: 1.35em;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    .content-grid + .content-grid,
    .content-grid + .content-section {
        margin-top: 20px;
    }

    .content-section.full-width-section + .content-section.full-width-section {
        margin-top: 20px;
    }

    /* Form Styling */
    .content-section > label:not(.toggle-row) {
        display: block;
        font-size: 13px;
        color: rgb(242, 124, 17);
        font-weight: 600;
        margin-bottom: 6px;
        margin-top: 14px;
    }

    .content-section > label:not(.toggle-row):first-of-type {
        margin-top: 0;
    }

    .content-section input[type="text"],
    .content-section input[type="number"] {
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 4px;
        transition: all 0.2s ease;
    }

    .content-section input[type="text"]:focus,
    .content-section input[type="number"]:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }
    
    .content-section textarea {
        min-height: 80px;
        font-family: inherit;
        resize: vertical;
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 4px;
        transition: all 0.2s ease;
    }

    .content-section textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }
    
    .content-section select {
        background-color: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        border: 1px solid #3a3a3a;
        padding: 10px 12px;
        border-radius: 6px;
        width: 100%;
        margin-bottom: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .content-section select:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    /* Toggle Switch Styling */
    .toggle-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: rgba(26, 26, 26, 0.6);
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.2s ease;
    }

    .toggle-row:hover {
        background: rgba(36, 36, 36, 0.8);
        border-color: #4a4a4a;
    }

    .toggle-switch {
        position: relative;
        width: 48px;
        height: 24px;
        flex-shrink: 0;
    }

    .toggle-switch input[type="checkbox"] {
        position: absolute;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        margin: 0;
        z-index: 2;
    }

    .toggle-slider {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #3a3a3a;
        border-radius: 24px;
        transition: all 0.3s ease;
        border: 1px solid #555;
    }

    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        top: 50%;
        transform: translateY(-50%);
        background-color: #888;
        border-radius: 50%;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .toggle-switch input[type="checkbox"]:checked + .toggle-slider {
        background-color: rgba(32, 122, 74, 0.9);
        border-color: rgba(72, 187, 120, 0.5);
    }

    .toggle-switch input[type="checkbox"]:checked + .toggle-slider::before {
        transform: translateY(-50%) translateX(22px);
        background-color: #fff;
    }

    .toggle-switch input[type="checkbox"]:focus + .toggle-slider {
        box-shadow: 0 0 0 3px rgba(32, 122, 74, 0.25);
    }

    .toggle-label {
        flex: 1;
        color: #e0e0e0;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        user-select: none;
    }

    .toggle-label:hover {
        color: #fff;
    }

    /* Legacy checkbox group - keep for dynamic profile fields */
    .checkbox-group {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }

    .checkbox-group label {
        margin: 0;
        font-weight: normal;
        color: #cfd8e3;
        cursor: pointer;
    }

    .checkbox-group input[type="checkbox"] {
        accent-color: #176529;
        transform: scale(1.4);
        margin-right: 8px;
        cursor: pointer;
    }

    /* Dynamic Profile Card */
    .dynamic-profile-card {
        margin-bottom: 20px;
        padding: 18px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(32, 32, 32, 0.6));
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        box-shadow: inset 0 1px rgba(255,255,255,0.03);
    }

    .dynamic-profile-card h3 {
        color: rgb(242, 124, 17);
        margin-bottom: 14px;
        font-size: 1.15em;
        font-weight: 600;
    }

    .field-selection-label {
        margin-top: 14px;
        display: block;
        color: rgb(242, 124, 17);
        font-weight: 600;
        font-size: 0.95em;
    }

    .field-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .field-chip {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(42, 42, 42, 0.8);
        border: 1px solid #4a4a4a;
        padding: 10px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .field-chip:hover {
        background: rgba(52, 52, 52, 0.9);
        border-color: #5a5a5a;
    }

    .field-chip:has(input:checked) {
        background: rgba(32, 122, 74, 0.25);
        border-color: rgba(72, 187, 120, 0.5);
    }

    .field-chip input[type="checkbox"] {
        accent-color: #176529;
        transform: scale(1.3);
        cursor: pointer;
    }

    .field-chip .chip-text {
        color: #cfd8e3;
        font-size: 0.95em;
        font-weight: 500;
    }

    .field-chips + .hint {
        margin-top: 10px;
    }

    .hint {
        font-size: 12px;
        color: #999;
        margin-top: 4px;
        margin-bottom: 6px;
        display: block;
        padding-left: 2px;
        line-height: 1.4;
    }

    .toggle-row + .hint {
        margin-left: 62px;
        margin-top: -2px;
        margin-bottom: 12px;
    }

    .toggle-row + .hint + label:not(.toggle-row) {
        margin-top: 8px;
    }

    /* Button Styling */
    .btn-save {
        background-color: #176529;
        color: #fff;
        border: 1px solid rgba(72, 187, 120, 0.3);
        border-radius: 8px;
        padding: 12px 28px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        letter-spacing: 0.3px;
        margin-bottom: 24px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255, 255, 255, 0.1);
    }

    .btn-save:hover {
        background-color: #1e8738;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                    inset 0 1px rgba(255, 255, 255, 0.15);
    }

    .btn-save:active {
        transform: translateY(0);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .narrator-settings-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .narrator-settings-actions .btn-save {
        margin-bottom: 0;
    }

    .btn-narrator-transfer {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        height: 36px;
        padding: 6px 12px;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        background: #333;
        color: #fff;
        cursor: pointer;
        font-size: 0.85em;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .btn-narrator-transfer:hover:not(:disabled) {
        background: #414141;
        border-color: rgba(242, 124, 17, 0.65);
        color: #fff;
        text-decoration: none;
    }

    .btn-narrator-transfer:disabled {
        opacity: 0.6;
        cursor: wait;
    }

    /* Toast Notification */
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #207a4a;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: none;
        z-index: 9999;
    }
    
    .toast-notification.error {
        background: #c03;
    }

    .advanced-prompts-wrap {
        margin-top: 24px;
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        background: linear-gradient(180deg, rgba(36, 36, 36, 0.92), rgba(28, 28, 28, 0.96));
        overflow: hidden;
    }

    .advanced-prompts-wrap[open] {
        border-color: rgba(242, 124, 17, 0.28);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18);
    }

    .advanced-prompts-summary {
        list-style: none;
        cursor: pointer;
        padding: 16px 18px;
        color: #ffffff;
        font-family: 'MagicCards', serif !important;
        font-size: 1.15em;
        word-spacing: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        user-select: none;
    }

    .advanced-prompts-summary::-webkit-details-marker {
        display: none;
    }

    .advanced-prompts-summary-text {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-family: 'MagicCards', serif !important;
    }

    .advanced-prompts-summary-text span {
        font-family: 'MagicCards', serif !important;
    }

    .advanced-prompts-summary-icon {
        font-size: 0.9em;
        transition: transform 0.2s ease;
    }

    .advanced-prompts-wrap[open] .advanced-prompts-summary-icon {
        transform: rotate(90deg);
    }

    .advanced-prompts-panel {
        padding: 0 18px 18px 18px;
    }

    .advanced-prompts-table-wrap {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        max-height: 560px;
        overflow-y: auto;
        overflow-x: auto;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .advanced-prompts-table-wrap thead {
        position: sticky;
        top: 0;
        z-index: 10;
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-bottom: 2px solid rgba(242, 124, 17, 0.5);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .advanced-prompts-table {
        width: 100%;
        border-collapse: collapse;
    }

    .advanced-prompts-table th {
        padding: 15px 12px;
        text-align: left;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.05em;
        word-spacing: 6px;
        font-weight: normal;
        letter-spacing: 1px;
    }

    .advanced-prompts-table tbody tr {
        border-bottom: 1px solid #3a3a3a;
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .advanced-prompts-table tbody tr:hover {
        background: rgba(242, 124, 17, 0.05);
        box-shadow: inset 0 0 10px rgba(242, 124, 17, 0.1);
    }

    .advanced-prompts-table td {
        padding: 12px;
        color: #e0e0e0;
        vertical-align: top;
    }

    .advanced-prompt-key-cell {
        min-width: 220px;
        font-family: 'Courier New', monospace;
        color: rgb(100, 149, 237);
        font-size: 0.9em;
    }

    .advanced-prompt-description-cell {
        min-width: 260px;
        color: #b0b0b0;
        font-style: italic;
        font-size: 0.9em;
    }

    .advanced-prompt-content-cell {
        max-width: 420px;
    }

    .advanced-prompt-preview {
        background: #1a1a1a;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        font-size: 0.85em;
        white-space: pre-wrap;
        max-height: 100px;
        overflow-y: auto;
        color: #ccc;
        line-height: 1.4;
    }

    .advanced-prompt-preview.custom {
        border-color: rgb(242, 124, 17);
        background: rgba(242, 124, 17, 0.05);
    }

    .advanced-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        text-align: center;
    }

    .advanced-status-badge.custom {
        background: rgba(242, 124, 17, 0.2);
        color: rgb(242, 124, 17);
        border: 1px solid rgb(242, 124, 17);
    }

    .advanced-status-badge.default {
        background: rgba(100, 149, 237, 0.2);
        color: rgb(100, 149, 237);
        border: 1px solid rgb(100, 149, 237);
    }

    .advanced-prompts-actions-cell {
        white-space: nowrap;
        text-align: center;
        min-width: 120px;
    }

    .advanced-prompts-btn {
        padding: 6px 12px;
        border: 1px solid rgba(58, 58, 58, 0.5);
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.2s ease;
        margin: 2px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .advanced-prompts-btn-edit {
        background: linear-gradient(135deg, rgba(100, 149, 237, 0.9), rgba(80, 129, 217, 0.9));
        color: white;
        border-color: rgba(100, 149, 237, 0.3);
    }

    .advanced-prompts-btn-edit:hover {
        background: linear-gradient(135deg, rgba(80, 129, 217, 1), rgba(60, 109, 197, 1));
        border-color: rgba(100, 149, 237, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .advanced-prompts-btn-clear {
        background: linear-gradient(135deg, rgba(242, 124, 17, 0.9), rgba(222, 104, 0, 0.9));
        color: white;
        border-color: rgba(242, 124, 17, 0.3);
    }

    .advanced-prompts-btn-clear:hover {
        background: linear-gradient(135deg, rgba(222, 104, 0, 1), rgba(202, 84, 0, 1));
        border-color: rgba(242, 124, 17, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .advanced-prompts-empty {
        padding: 18px;
        color: #cfd8e3;
    }

    .narrator-actions-summary-count {
        font-family: Arial, sans-serif;
        font-size: 0.85rem;
        color: #cfd8e3;
        word-spacing: normal;
    }

    .narrator-actions-table td:first-child {
        min-width: 240px;
    }

    .narrator-actions-name {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .narrator-actions-name strong {
        color: #f1f4f8;
        font-size: 0.98rem;
    }

    .narrator-actions-name code {
        color: rgb(100, 149, 237);
        font-size: 0.85rem;
    }

    .narrator-actions-description {
        min-width: 320px;
        color: #b8c2d1;
        line-height: 1.45;
    }

    .narrator-actions-state {
        min-width: 110px;
    }

    .narrator-actions-toggle-cell {
        white-space: nowrap;
        min-width: 120px;
    }

    .narrator-actions-toggle-btn {
        min-width: 96px;
    }

    .narrator-actions-toggle-btn[disabled] {
        opacity: 0.65;
        cursor: wait;
    }

    .narrator-actions-empty {
        padding: 18px;
        color: #cfd8e3;
    }

    .advanced-prompts-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.8);
    }

    .advanced-prompts-modal-content {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
        margin: 2% auto;
        padding: 0;
        border: 2px solid rgba(242, 124, 17, 0.5);
        border-radius: 10px;
        width: 90%;
        max-width: 1200px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .advanced-prompts-modal-header {
        padding: 20px;
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-bottom: 1px solid rgba(242, 124, 17, 0.3);
        border-radius: 8px 8px 0 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .advanced-prompts-modal-header h3 {
        margin: 0;
        color: #ffffff;
        font-family: 'MagicCards', serif;
        font-size: 1.5em;
        word-spacing: 6px;
    }

    .advanced-prompts-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }

    .advanced-prompts-modal-footer {
        padding: 15px 20px;
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-top: 1px solid rgba(242, 124, 17, 0.3);
        text-align: right;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.2);
    }

    .advanced-prompts-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        line-height: 20px;
        cursor: pointer;
    }

    .advanced-prompts-modal-close:hover,
    .advanced-prompts-modal-close:focus {
        color: rgb(242, 124, 17);
    }

    .advanced-prompts-modal-group {
        margin-bottom: 20px;
    }

    .advanced-prompts-modal-group label {
        display: block;
        margin-bottom: 8px;
        color: rgb(242, 124, 17);
        font-weight: bold;
    }

    .advanced-prompts-readonly {
        background: linear-gradient(135deg, rgba(37, 37, 37, 0.8), rgba(32, 32, 32, 0.9));
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        color: #999;
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
        line-height: 1.5;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .advanced-prompts-textarea {
        width: 100%;
        min-height: 300px;
        resize: vertical;
        padding: 12px;
        background: rgba(26, 26, 26, 0.8);
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        color: #e0e0e0;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.5;
        transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    }

    .advanced-prompts-textarea:focus {
        outline: none;
        border-color: rgb(242, 124, 17);
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
        background: rgba(26, 26, 26, 0.95);
    }

    .advanced-prompts-modal-btn {
        padding: 10px 18px;
        border: 1px solid rgba(58, 58, 58, 0.5);
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.95em;
        transition: all 0.2s ease;
        margin-left: 8px;
    }

    .advanced-prompts-modal-btn-cancel {
        background: linear-gradient(135deg, rgba(136, 136, 136, 0.9), rgba(102, 102, 102, 0.9));
        color: white;
        border-color: rgba(136, 136, 136, 0.3);
    }

    .advanced-prompts-modal-btn-save {
        background: linear-gradient(135deg, rgba(76, 175, 80, 0.9), rgba(69, 160, 73, 0.9));
        color: white;
        border-color: rgba(76, 175, 80, 0.3);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            padding: 15px;
        }
        
        .content-section {
            padding: 15px;
        }

        .advanced-prompts-modal-content {
            width: 95%;
            margin: 5% auto;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .page-header h1 {
            font-size: 1.5em;
        }

        .toggle-row {
            padding: 10px 12px;
        }

        .toggle-label {
            font-size: 13px;
        }

        .field-chips {
            flex-direction: column;
        }

        .toggle-row + .hint {
            margin-left: 0;
        }
    }
</style>

<?php if ($isEmbed): ?>
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 20px; }
</style>
<?php endif; ?>

<main class="player-narration-settings narration-settings-page<?php echo $isEmbed ? ' is-embedded' : ''; ?>">
    <div class="page-container">
        <div id="toast" class="toast-notification <?php echo $saveSuccess ? '' : 'error'; ?>">
            <span class="message"><?php echo htmlspecialchars($saveMessage); ?></span>
        </div>

        <?php if ($saveSuccess || $saveMessage): ?>
            <script>
            setTimeout(function(){ 
                try{ 
                    const t=document.getElementById('toast'); 
                    if(t){ 
                        t.style.display='block'; 
                        setTimeout(()=>{ t.style.display='none'; }, 3000); 
                    } 
                }catch(_e){} 
            }, 50);
            </script>
        <?php endif; ?>

        <div class="page-header chim-page-head">
            <h1 class="chim-page-head-title">🗣️ Narrator Management</h1>
            <div class="chim-page-head-note">
                <p>Configure narrator behavior and settings</p>
            </div>
        </div>

        <form method="post" action="">
            <div class="narrator-settings-actions settings-page-actions">
                <button type="submit" class="btn-save" name="save_narrator" value="1">Save Narration Settings</button>
                <a class="btn-narrator-transfer" href="<?php echo $webRoot; ?>/ui/cmd/settings_portability.php?scope=narration&amp;action=export">&#128228; Export Narration</a>
                <button type="button" class="btn-narrator-transfer" id="import_narration_settings_btn">&#128229; Import Narration</button>
                <input type="file" id="import_narration_settings_file" accept="application/json,.json" hidden>
            </div>

            <div class="content-grid">
                <!-- Core Settings Section -->
                <div class="content-section">
                    <h2>Core Settings</h2>

                    <label for="roleplay_name">Narrator Name</label>
                    <input type="text" id="roleplay_name" name="roleplay_name" maxlength="64" value="<?php echo htmlspecialchars($roleplayName); ?>" placeholder="The Narrator">
                    <span class="hint">Changes how the narrator is identified in prompts, LLM context, subtitles, and history displays. Internal routing, storage, actions, and TTS continue to use The Narrator.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="enabled" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Narrator</span>
                    </label>
                    <span class="hint">Enable or disable the narrator system entirely.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="books_only_narrator" name="books_only_narrator" value="1" <?php echo $booksOnlyNarrator ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Only the Narrator can Summarize Books</span>
                    </label>
                    <span class="hint">The Narrator will be the only one to summarize books when you trigger the book summary feature.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="hide_from_context" name="hide_from_context" value="1" <?php echo $hideFromContext ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Hide Narrator from NPC Context</span>
                    </label>
                    <span class="hint">Hide Narrator-spoken dialogue lines from NPC context.</span>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="diary_enabled" name="diary_enabled" value="1" <?php echo $diaryEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Narrator Diary</span>
                    </label>
                    <span class="hint">Allow The Narrator to write manual diary entries from narrator-targeted diary actions.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="auto_diary_enabled" name="auto_diary_enabled" value="1" <?php echo $autoDiaryEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Narrator Auto Diary</span>
                    </label>
                    <span class="hint">Allow The Narrator to join sleep and wait auto-diary generation. This does not affect manual narrator diary actions.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="only_diary_access" name="only_diary_access" value="1" <?php echo $onlyDiaryAccess ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Narrator only diary access</span>
                    </label>
                    <span class="hint">Restrict the Narrator to diary entries written by The Narrator. When disabled, the Narrator may recall relevant diary entries from all NPCs.</span>
                </div>

                <!-- Narration Section -->
                <div class="content-section">
                    <h2>Inline Narration</h2>

                    <label for="inline_narration_mode">Inline Narration Mode</label>
                    <select id="inline_narration_mode" name="inline_narration_mode">
                        <option value="disabled" <?php echo $inlineNarrationMode === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        <option value="narrator" <?php echo $inlineNarrationMode === 'narrator' ? 'selected' : ''; ?>>Narrator</option>
                        <option value="npc" <?php echo $inlineNarrationMode === 'npc' ? 'selected' : ''; ?>>NPC</option>
                        <option value="text_only" <?php echo $inlineNarrationMode === 'text_only' ? 'selected' : ''; ?>>Text Only</option>
                    </select>
                    <span class="hint">Controls leading *narration* blocks. Narrator uses The Narrator voice, NPC speaks the full line, Text Only displays the narration but speaks only the dialogue, and Disabled turns off special routing.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="remove_asterisks_from_player_input" name="remove_asterisks_from_player_input" value="1" <?php echo $removeAsterisksFromPlayerInput ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Remove Player Input Asterisks From TTS</span>
                    </label>
                    <span class="hint">Filters *asterisked* player actions/emphasis from player TTS only. The in-game player subtitle echo keeps the visible *text* either way. Turn this off if you want the player voice to speak asterisked text too.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="remove_asterisks_from_npc_output" name="remove_asterisks_from_npc_output" value="1" <?php echo $removeAsterisksFromNpcOutput ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Remove NPC Output Asterisks</span>
                    </label>
                    <span class="hint">Filters *asterisked* NPC narration/emotes from NPC speech and subtitles. Text Only always keeps leading narration visible. Turn this off if you want NPCs in other modes to keep or speak their own asterisked text.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="remove_player_autochat_asterisks" name="remove_player_autochat_asterisks" value="1" <?php echo $removePlayerAutochatAsterisks ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Remove Player Autochat Astreisk</span>
                    </label>
                    <span class="hint">Keeps AUTOCHAT and `**` player respeech spoken-only by stripping leading narration and *asterisked* narration from the rewritten player line. Turn this off if you want rewritten player text to keep one short leading *narration* block.</span>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="preserve_asterisks_in_context" name="preserve_asterisks_in_context" value="1" <?php echo $preserveAsterisksInContext ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Keep NPC Narration Description in Context History</span>
                    </label>
                    <span class="hint">Keep *narration* descriptions intact in the eventlog context history. NPCs will be able to see these descriptions in their prompts..</span>
                </div>

                <!-- Welcome Message Section -->
                <div class="content-section">
                    <h2>Welcome Message</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="welcome_enabled" name="welcome_enabled" value="1" <?php echo $welcomeEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Welcome Message on Load</span>
                    </label>
                    <span class="hint">The Narrator will give you a quick recap of what happened previously after you have loaded a save game.</span>
                    
                    <label for="welcome_cooldown">Welcome Message Cooldown (minutes)</label>
                    <input type="number" id="welcome_cooldown" name="welcome_cooldown" value="<?php echo htmlspecialchars((string)$welcomeCooldown); ?>" min="1" max="1440">
                    <span class="hint">Minimum time in minutes between welcome messages. Range: 1-1440 (24 hours), Default: 10 minutes</span>
                </div>

                <!-- Random Narration Section -->
                <div class="content-section">
                    <h2>Random Narration</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="random_enabled" name="random_enabled" value="1" <?php echo $randomEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Random Narration</span>
                    </label>
                    <span class="hint">Enable random Narrator interjections. The Narrator will occasionally add visual scene descriptions during conversations.</span>
                    
                    <label for="random_chance">Random Narration Chance (%)</label>
                    <input type="number" id="random_chance" name="random_chance" value="<?php echo htmlspecialchars((string)$randomChance); ?>" min="1" max="100">
                    <span class="hint">Probability (1-100) that the Narrator will interject with a scene description. Default: 15%</span>
                    
                    <label for="random_cooldown">Random Narration Cooldown</label>
                    <input type="number" id="random_cooldown" name="random_cooldown" value="<?php echo htmlspecialchars((string)$randomCooldown); ?>" min="0" max="10">
                    <span class="hint">Minimum number of conversation rounds between Narrator interjections. Prevents narration spam. Range: 0-10, Default: 2</span>
                </div>

                <div class="content-section">
                    <h2>Bored Events</h2>

                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="bored_enabled" name="bored_enabled" value="1" <?php echo $boredEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Allow Narrator Bored Events</span>
                    </label>
                    <span class="hint">When enabled, bored events can sometimes route through The Narrator instead of the selected NPC.</span>

                    <label for="bored_chance">Narrator Bored Event Chance (%)</label>
                    <input type="number" id="bored_chance" name="bored_chance" value="<?php echo htmlspecialchars((string)$boredChance); ?>" min="1" max="100">
                    <span class="hint">Probability (1-100) that an eligible bored event will be rerouted to The Narrator. Default: 25%</span>
                </div>

                <!-- Quest Comments Section -->
                <div class="content-section">
                    <h2>Quest Comments</h2>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="quest_comment_enabled" name="quest_comment_enabled" value="1" <?php echo $questCommentEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Quest Comments</span>
                    </label>
                    <span class="hint">Narrator will comment on quest objective updates.</span>
                    
                    <label for="quest_comment_chance">Quest Comment Chance (%)</label>
                    <input type="number" id="quest_comment_chance" name="quest_comment_chance" value="<?php echo htmlspecialchars((string)$questCommentChance); ?>" min="1" max="100">
                    <span class="hint">Probability (1-100) that Narrator will comment on quest updates. Default: 10%</span>
                    
                    <label for="quest_comment_cooldown">Quest Comment Cooldown (minutes)</label>
                    <input type="number" id="quest_comment_cooldown" name="quest_comment_cooldown" value="<?php echo htmlspecialchars((string)$questCommentCooldown); ?>" min="1" max="60">
                    <span class="hint">Minimum time in minutes between quest comments. Prevents spam. Range: 1-60 minutes, Default: 3 minutes</span>
                </div>
            </div>
            
            <!-- Profile & Voice Section -->
            <div class="content-grid">
                <div class="content-section">
                    <h2>Profile & Voice</h2>

                    <label for="profile_id">Profile</label>
                    <select id="profile_id" name="profile_id">
                        <?php foreach ($allProfiles as $profile): ?>
                            <option value="<?php echo htmlspecialchars((string)$profile['id']); ?>" <?php echo ($profileId == $profile['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($profile['label'] ?? 'Profile ' . $profile['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">LLM connector profile for The Narrator.</span>
                    
                    <label for="voiceid">Voice ID</label>
                    <input type="text" id="voiceid" name="voiceid" value="<?php echo htmlspecialchars($voiceid); ?>" placeholder="TheNarrator">
                    <span class="hint">TTS voice identifier for The Narrator.</span>
                    
                    <label for="oghma_knowledge">Oghma Knowledge Tags</label>
                    <input type="text" id="oghma_knowledge" name="oghma_knowledge" placeholder="Comma-separated knowledge tags (e.g., knowall, knowsome, knownone)" value="<?php echo htmlspecialchars($oghmaKnowledge); ?>">
                    <span class="hint">Comma-separated knowledge tags used by Oghma systems for knowledge lookup restrictions.</span>
                </div>
                
                <div class="content-section">
                    <h2>Selected Profile Connectors</h2>
                    <?php
                    // Helper function to get connector label
                    $getConnectorLabel = function($id) use ($llmById) {
                        return htmlspecialchars($llmById[$id] ?? '—');
                    };
                    ?>
                    <div id="profile_llm_summary" style="display:grid; grid-template-columns: auto 1fr; gap:8px; color:#cfd9ea; font-size: 13px; line-height: 1.6;">
                        <div style="color:rgb(242,124,17); font-weight:600;">🔊 TTS:</div>
                        <div><?= htmlspecialchars($ttsById[$currentProfileData['tts_connector_id'] ?? null] ?? '—') ?></div>
                        <div style="color:rgb(242,124,17); font-weight:600;">🕹️ Standard:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_primary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">🏃‍♂️‍➡️ Fast:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_secondary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">💪 Power:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_tertiary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">🧪 Experimental:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_quaternary_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">📓 Diary:</div>
                        <div><?= $getConnectorLabel($currentProfileData['diary_connector_id'] ?? null) ?></div>
                        
                        <div style="color:rgb(242,124,17); font-weight:600;">🧾 Formatter:</div>
                        <div><?= $getConnectorLabel($currentProfileData['llm_formatter_id'] ?? null) ?></div>
                    </div>
                    <span class="hint" style="margin-top: 8px;">These connectors are configured in the selected profile and will be used for The Narrator's AI responses.</span>
                </div>
            </div>
            
            <script>
            (function(){
                const PROFILE_CONN = <?= json_encode($profilesConnById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                const LLM_LABELS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                const TTS_LABELS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
                
                function labelOf(id){ 
                    const k = String(id || ''); 
                    return (k && LLM_LABELS[k]) ? String(LLM_LABELS[k]) : '—'; 
                }
                
                function ttsLabelOf(id){
                    const k = String(id || '');
                    return (k && TTS_LABELS[k]) ? String(TTS_LABELS[k]) : '—';
                }
                
                function renderProfileConnectors(pid){
                    const box = document.getElementById('profile_llm_summary');
                    if (!box) return;
                    const pc = PROFILE_CONN[String(pid || '')] || null;
                    
                    const rows = [
                        ['🔊 TTS:', ttsLabelOf(pc ? pc.tts_connector_id : null)],
                        ['🕹️ Standard:', labelOf(pc ? pc.llm_primary_id : null)],
                        ['🏃‍♂️‍➡️ Fast:', labelOf(pc ? pc.llm_secondary_id : null)],
                        ['💪 Power:', labelOf(pc ? pc.llm_tertiary_id : null)],
                        ['🧪 Experimental:', labelOf(pc ? pc.llm_quaternary_id : null)],
                        ['📓 Diary:', labelOf(pc ? pc.diary_connector_id : null)],
                        ['🧾 Formatter:', labelOf(pc ? pc.llm_formatter_id : null)]
                    ];
                    
                    let html = '';
                    rows.forEach(([k, v]) => {
                        html += '<div style="color:rgb(242,124,17); font-weight:600;">' + k + '</div>';
                        html += '<div>' + String(v || '—') + '</div>';
                    });
                    box.innerHTML = html;
                }
                
                // Update on profile change
                const profileSelect = document.getElementById('profile_id');
                if (profileSelect) {
                    profileSelect.addEventListener('change', function() {
                        renderProfileConnectors(this.value);
                    });
                }
            })();
            </script>
            
            <!-- Prompt Head Override Section -->
            <div class="content-section full-width-section">
                <h2>Prompt Head Override</h2>
                <label for="prompt_head">Custom Prompt Head</label>
                <textarea id="prompt_head" name="prompt_head" rows="5" placeholder="High-level system instructions injected before the core..."><?php echo htmlspecialchars($promptHead); ?></textarea>
                <span class="hint">System preamble inserted before other sections. This overrides the profile and global prompt head when The Narrator is active. Leave empty to use profile/global defaults.</span>
            </div>
            
            <!-- Character Fields Section -->
            <div class="content-section full-width-section">
                <h2>Character Description</h2>
                
                <!-- Dynamic Profile Section (inline) -->
                <div class="dynamic-profile-card">
                    <h3>♻️ Dynamic Profile Updates</h3>
                    
                    <label class="toggle-row">
                        <div class="toggle-switch">
                            <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?php echo $dynamicProfileEnabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-label">Enable Dynamic Profile</span>
                    </label>
                    <span class="hint">Allow systems to evolve the narrator profile based on gameplay events. Triggered by MCM Dynamic Profile Timer.</span>
                    
                    <label class="field-selection-label">Field Selection (choose 1-3)</label>
                    <span class="hint">Select which fields should be dynamically updated:</span>
                    
                    <div class="field-chips">
                        <label class="field-chip">
                            <input type="checkbox" name="dynamic_profile_fields[]" value="personality" <?php echo in_array('personality', $dynamicProfileFields) ? 'checked' : ''; ?>>
                            <span class="chip-text">Personality</span>
                        </label>
                        <label class="field-chip">
                            <input type="checkbox" name="dynamic_profile_fields[]" value="speechstyle" <?php echo in_array('speechstyle', $dynamicProfileFields) ? 'checked' : ''; ?>>
                            <span class="chip-text">Speech Style</span>
                        </label>
                        <label class="field-chip">
                            <input type="checkbox" name="dynamic_profile_fields[]" value="goals" <?php echo in_array('goals', $dynamicProfileFields) ? 'checked' : ''; ?>>
                            <span class="chip-text">Goals</span>
                        </label>
                    </div>
                    <span class="hint">Recommended: Select only 1-3 fields. Updates use DYNAMIC_PROMPT_* prompts from Global Settings.</span>
                </div>
                
                <label for="core">Core Summary</label>
                <textarea id="core" name="core" rows="3" placeholder="Quick summary of The Narrator's persona..."><?php echo htmlspecialchars($core); ?></textarea>
                <span class="hint">Brief summary of The Narrator's role and personality.</span>
                
                <label for="background">Background</label>
                <textarea id="background" name="background" rows="4" placeholder="Background description..."><?php echo htmlspecialchars($background); ?></textarea>
                <span class="hint">Detailed background and history of The Narrator.</span>
                
                <label for="personality">Personality</label>
                <textarea id="personality" name="personality" rows="3" placeholder="Personality traits..."><?php echo htmlspecialchars($personality); ?></textarea>
                <span class="hint">Behavioral traits and personality characteristics.</span>
                
                <label for="speechstyle">Speech Style</label>
                <textarea id="speechstyle" name="speechstyle" rows="2" placeholder="How The Narrator speaks..."><?php echo htmlspecialchars($speechstyle); ?></textarea>
                <span class="hint">How The Narrator communicates and speaks.</span>
                
                <label for="goals">Goals</label>
                <textarea id="goals" name="goals" rows="3" placeholder="Current objectives..."><?php echo htmlspecialchars($goals); ?></textarea>
                <span class="hint">Current goals and objectives for The Narrator.</span>

                <details class="advanced-prompts-wrap">
                    <summary class="advanced-prompts-summary">
                        <span class="advanced-prompts-summary-text">
                            <span class="advanced-prompts-summary-icon">▶</span>
                            <span>Narration Actions</span>
                        </span>
                        <span class="narrator-actions-summary-count" id="narratorActionsSummaryCount">
                            <?php echo intval($narratorActionStats['enabled'] ?? 0); ?> enabled / <?php echo intval($narratorActionStats['total'] ?? 0); ?> total
                        </span>
                    </summary>
                    <div class="advanced-prompts-panel">
                        <?php if (!empty($narratorActionRows)): ?>
                            <div class="advanced-prompts-table-wrap">
                                <table class="advanced-prompts-table narrator-actions-table">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Toggle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($narratorActionRows as $actionRow): ?>
                                            <?php
                                            $actionCodeName = strval($actionRow['code_name'] ?? '');
                                            $actionName = strval($actionRow['action_name'] ?? $actionCodeName);
                                            $actionDescription = strval($actionRow['description'] ?? '');
                                            $actionEnabled = herikaActionCatalogToBool($actionRow['is_activated'] ?? false);
                                            $nextActionState = $actionEnabled ? '0' : '1';
                                            ?>
                                            <tr id="narrator-action-row-<?php echo htmlspecialchars($actionCodeName, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td>
                                                    <div class="narrator-actions-name">
                                                        <strong><?php echo htmlspecialchars($actionName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <code><?php echo htmlspecialchars($actionCodeName, ENT_QUOTES, 'UTF-8'); ?></code>
                                                    </div>
                                                </td>
                                                <td class="narrator-actions-description"><?php echo htmlspecialchars($actionDescription, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="narrator-actions-state">
                                                    <?php if ($actionEnabled): ?>
                                                        <span class="advanced-status-badge custom">Enabled</span>
                                                    <?php else: ?>
                                                        <span class="advanced-status-badge default">Disabled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="narrator-actions-toggle-cell">
                                                    <button
                                                        type="button"
                                                        class="advanced-prompts-btn narrator-actions-toggle-btn <?php echo $actionEnabled ? 'advanced-prompts-btn-clear' : 'advanced-prompts-btn-edit'; ?>"
                                                        data-code-name="<?php echo htmlspecialchars($actionCodeName, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-target-enabled="<?php echo htmlspecialchars($nextActionState, ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="toggleNarratorAction(this)"
                                                    >
                                                        <?php echo $actionEnabled ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="narrator-actions-empty">No narrator-scoped actions were found.</div>
                        <?php endif; ?>
                    </div>
                </details>

                <details class="advanced-prompts-wrap">
                    <summary class="advanced-prompts-summary">
                        <span class="advanced-prompts-summary-text">
                            <span class="advanced-prompts-summary-icon">▶</span>
                            <span>Advanced Prompts (Prompts Manager)</span>
                        </span>
                    </summary>
                    <div class="advanced-prompts-panel">
                        <?php if (!empty($advancedPrompts)): ?>
                            <div class="advanced-prompts-table-wrap">
                                <table class="advanced-prompts-table">
                                    <thead>
                                        <tr>
                                            <th>Prompt Key</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Preview</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($advancedPrompts as $promptRow):
                                            $promptKey = htmlspecialchars($promptRow['prompt_key']);
                                            $defaultPrompt = $promptRow['default_prompt'] ?? '';
                                            $customPrompt = $promptRow['custom_prompt'] ?? '';
                                            $description = htmlspecialchars($promptRow['description'] ?? '');
                                            $isCustomPrompt = !empty($customPrompt);
                                            $activePrompt = $isCustomPrompt ? $customPrompt : $defaultPrompt;
                                            $preview = strlen($activePrompt) > 150 ? substr($activePrompt, 0, 150) . '...' : $activePrompt;
                                        ?>
                                            <tr id="advanced-prompt-row-<?php echo htmlspecialchars($promptRow['prompt_key']); ?>">
                                                <td class="advanced-prompt-key-cell">
                                                    <code><?php echo $promptKey; ?></code>
                                                </td>
                                                <td class="advanced-prompt-description-cell"><?php echo $description; ?></td>
                                                <td>
                                                    <?php if ($isCustomPrompt): ?>
                                                        <span class="advanced-status-badge custom">Custom</span>
                                                    <?php else: ?>
                                                        <span class="advanced-status-badge default">Default</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="advanced-prompt-content-cell">
                                                    <div class="advanced-prompt-preview <?php echo $isCustomPrompt ? 'custom' : ''; ?>">
                                                        <?php echo htmlspecialchars($preview); ?>
                                                    </div>
                                                </td>
                                                <td class="advanced-prompts-actions-cell">
                                                    <button type="button" class="advanced-prompts-btn advanced-prompts-btn-edit" onclick="openNarratorPromptModal('<?php echo $promptKey; ?>')">Edit</button>
                                                    <?php if ($isCustomPrompt): ?>
                                                        <button type="button" class="advanced-prompts-btn advanced-prompts-btn-clear" onclick="clearNarratorPrompt('<?php echo $promptKey; ?>')">Clear</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="advanced-prompts-empty">No advanced narrator prompts were found.</div>
                        <?php endif; ?>
                    </div>
                </details>
            </div>

            <div style="display:flex; justify-content:flex-start; margin-top:20px;">
                <button type="submit" class="btn-save" name="save_narrator" value="1">Save Narration Settings</button>
            </div>
        </form>

        <div id="narratorAdvancedPromptModal" class="advanced-prompts-modal">
            <div class="advanced-prompts-modal-content">
                <div class="advanced-prompts-modal-header">
                    <span class="advanced-prompts-modal-close" onclick="closeNarratorPromptModal()">&times;</span>
                    <h3>Edit Prompt: <span id="narratorAdvancedPromptModalKey"></span></h3>
                </div>
                <div class="advanced-prompts-modal-body">
                    <div class="advanced-prompts-modal-group">
                        <label>Description</label>
                        <p id="narratorAdvancedPromptModalDescription" style="color:#b0b0b0; margin:0;"></p>
                    </div>
                    <div class="advanced-prompts-modal-group">
                        <label>Default Prompt (Read-Only)</label>
                        <div id="narratorAdvancedPromptModalDefault" class="advanced-prompts-readonly"></div>
                    </div>
                    <div class="advanced-prompts-modal-group">
                        <label>Custom Prompt (Optional - Leave empty to use default)</label>
                        <textarea id="narratorAdvancedPromptModalCustom" class="advanced-prompts-textarea" placeholder="Enter your custom prompt here, or leave empty to use the default prompt..."></textarea>
                    </div>
                </div>
                <div class="advanced-prompts-modal-footer">
                    <button type="button" class="advanced-prompts-modal-btn advanced-prompts-modal-btn-cancel" onclick="closeNarratorPromptModal()">Cancel</button>
                    <button type="button" class="advanced-prompts-modal-btn advanced-prompts-modal-btn-save" onclick="saveNarratorPrompt()">Save Custom Prompt</button>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?php echo $webRoot; ?>/ui/js/settings-portability.js?v=<?php echo (int) @filemtime(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'settings-portability.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.chimInitSettingsImport === 'function') {
        window.chimInitSettingsImport({
            scope: 'narration',
            endpoint: <?php echo json_encode($webRoot . '/ui/cmd/settings_portability.php'); ?>,
            importButtonId: 'import_narration_settings_btn',
            fileInputId: 'import_narration_settings_file'
        });
    }
});
</script>

<script>
const narratorAdvancedPrompts = <?php
    $advancedPromptJs = [];
    foreach ($advancedPrompts as $promptRow) {
        $advancedPromptJs[$promptRow['prompt_key']] = [
            'default_prompt' => $promptRow['default_prompt'] ?? '',
            'custom_prompt' => $promptRow['custom_prompt'] ?? null,
            'description' => $promptRow['description'] ?? '',
        ];
    }
    echo json_encode($advancedPromptJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;
const narratorActions = <?php
    $narratorActionJs = [];
    foreach ($narratorActionRows as $actionRow) {
        $codeName = strval($actionRow['code_name'] ?? '');
        if ($codeName === '') {
            continue;
        }
        $narratorActionJs[$codeName] = [
            'action_name' => strval($actionRow['action_name'] ?? $codeName),
            'description' => strval($actionRow['description'] ?? ''),
            'enabled' => herikaActionCatalogToBool($actionRow['is_activated'] ?? false),
        ];
    }
    echo json_encode($narratorActionJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;

function showNarratorToast(message, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) {
        return;
    }

    const messageNode = toast.querySelector('.message');
    if (messageNode) {
        messageNode.textContent = message;
    }

    toast.classList.toggle('error', !!isError);
    toast.style.display = 'block';
    window.clearTimeout(window.__narratorToastTimer);
    window.__narratorToastTimer = window.setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

function updateNarratorActionSummary(stats = null) {
    const summary = document.getElementById('narratorActionsSummaryCount');
    if (!summary) {
        return;
    }

    let enabled = 0;
    let total = 0;
    if (stats && Number.isFinite(Number(stats.enabled)) && Number.isFinite(Number(stats.total))) {
        enabled = Number(stats.enabled);
        total = Number(stats.total);
    } else {
        const actionValues = Object.values(narratorActions);
        total = actionValues.length;
        enabled = actionValues.filter(action => !!action.enabled).length;
    }

    summary.textContent = `${enabled} enabled / ${total} total`;
}

function updateNarratorActionRow(codeName) {
    const row = document.getElementById('narrator-action-row-' + codeName);
    const actionData = narratorActions[codeName];
    if (!row || !actionData) {
        return;
    }

    const enabled = !!actionData.enabled;
    const statusCell = row.querySelector('.narrator-actions-state');
    if (statusCell) {
        statusCell.innerHTML = enabled
            ? '<span class="advanced-status-badge custom">Enabled</span>'
            : '<span class="advanced-status-badge default">Disabled</span>';
    }

    const button = row.querySelector('.narrator-actions-toggle-btn');
    if (button) {
        button.textContent = enabled ? 'Disable' : 'Enable';
        button.dataset.targetEnabled = enabled ? '0' : '1';
        button.classList.toggle('advanced-prompts-btn-clear', enabled);
        button.classList.toggle('advanced-prompts-btn-edit', !enabled);
    }
}

function toggleNarratorAction(button) {
    if (!button) {
        return;
    }

    const codeName = button.dataset.codeName || '';
    if (!codeName || !Object.prototype.hasOwnProperty.call(narratorActions, codeName)) {
        return;
    }

    const targetEnabled = button.dataset.targetEnabled === '1';
    const previousLabel = button.textContent;
    button.disabled = true;
    button.textContent = targetEnabled ? 'Enabling...' : 'Disabling...';

    const formData = new FormData();
    formData.append('action', 'toggle_narrator_action');
    formData.append('code_name', codeName);
    formData.append('target_enabled', targetEnabled ? '1' : '0');

    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to update narration action.');
        }

        narratorActions[codeName].enabled = !!data.enabled;
        updateNarratorActionRow(codeName);
        updateNarratorActionSummary(data.stats || null);
        showNarratorToast(data.message || 'Narration action updated.');
    })
    .catch(error => {
        button.textContent = previousLabel;
        showNarratorToast(error.message || 'Failed to update narration action.', true);
    })
    .finally(() => {
        button.disabled = false;
    });
}

function openNarratorPromptModal(promptKey) {
    const promptData = narratorAdvancedPrompts[promptKey];
    if (!promptData) {
        return;
    }

    document.getElementById('narratorAdvancedPromptModalKey').textContent = promptKey;
    document.getElementById('narratorAdvancedPromptModalDescription').textContent = promptData.description || 'No description available.';
    document.getElementById('narratorAdvancedPromptModalDefault').textContent = promptData.default_prompt || '';
    document.getElementById('narratorAdvancedPromptModalCustom').value = promptData.custom_prompt || '';
    document.getElementById('narratorAdvancedPromptModal').dataset.promptKey = promptKey;
    document.getElementById('narratorAdvancedPromptModal').style.display = 'block';
}

function closeNarratorPromptModal() {
    const modal = document.getElementById('narratorAdvancedPromptModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function updateNarratorPromptRow(promptKey) {
    const promptData = narratorAdvancedPrompts[promptKey];
    const row = document.getElementById('advanced-prompt-row-' + promptKey);
    if (!promptData || !row) {
        return;
    }

    const isCustom = !!(promptData.custom_prompt && String(promptData.custom_prompt).trim());
    const activePrompt = isCustom ? String(promptData.custom_prompt) : String(promptData.default_prompt || '');
    const preview = activePrompt.length > 150 ? activePrompt.substring(0, 150) + '...' : activePrompt;

    const statusCell = row.cells[2];
    statusCell.innerHTML = isCustom
        ? '<span class="advanced-status-badge custom">Custom</span>'
        : '<span class="advanced-status-badge default">Default</span>';

    const previewDiv = row.querySelector('.advanced-prompt-preview');
    if (previewDiv) {
        previewDiv.textContent = preview;
        previewDiv.classList.toggle('custom', isCustom);
    }

    const actionsCell = row.querySelector('.advanced-prompts-actions-cell');
    if (actionsCell) {
        let html = '<button type="button" class="advanced-prompts-btn advanced-prompts-btn-edit" onclick="openNarratorPromptModal(\'' + promptKey + '\')">Edit</button>';
        if (isCustom) {
            html += '<button type="button" class="advanced-prompts-btn advanced-prompts-btn-clear" onclick="clearNarratorPrompt(\'' + promptKey + '\')">Clear</button>';
        }
        actionsCell.innerHTML = html;
    }
}

function saveNarratorPrompt() {
    const modal = document.getElementById('narratorAdvancedPromptModal');
    const promptKey = modal ? modal.dataset.promptKey : '';
    if (!promptKey || !narratorAdvancedPrompts[promptKey]) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_narrator_prompt');
    formData.append('prompt_key', promptKey);
    formData.append('custom_prompt', document.getElementById('narratorAdvancedPromptModalCustom').value);

    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to save prompt.');
        }

        const nextValue = document.getElementById('narratorAdvancedPromptModalCustom').value;
        narratorAdvancedPrompts[promptKey].custom_prompt = nextValue.trim() === '' ? null : nextValue;
        updateNarratorPromptRow(promptKey);
        closeNarratorPromptModal();
        showNarratorToast('Prompt saved successfully.');
    })
    .catch(error => {
        showNarratorToast(error.message || 'Failed to save prompt.', true);
    });
}

function clearNarratorPrompt(promptKey) {
    if (!confirm('Are you sure you want to clear the custom prompt and revert to the default? This cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'clear_narrator_prompt');
    formData.append('prompt_key', promptKey);

    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to clear prompt.');
        }

        narratorAdvancedPrompts[promptKey].custom_prompt = null;
        updateNarratorPromptRow(promptKey);

        const modal = document.getElementById('narratorAdvancedPromptModal');
        if (modal && modal.style.display === 'block' && modal.dataset.promptKey === promptKey) {
            document.getElementById('narratorAdvancedPromptModalCustom').value = '';
        }

        showNarratorToast('Custom prompt cleared.');
    })
    .catch(error => {
        showNarratorToast(error.message || 'Failed to clear prompt.', true);
    });
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('narratorAdvancedPromptModal');
    if (modal && event.target === modal) {
        closeNarratorPromptModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeNarratorPromptModal();
    }
});
</script>

<?php
if (!$isEmbed) {
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
    $buffer = ob_get_contents();
    ob_end_clean();
    $title = $TITLE;
    $buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
    echo $buffer;
}
?>

