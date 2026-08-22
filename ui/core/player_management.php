<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "player_diary_connector.php");

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

$ttsConnector = new TTSConnector();
$llmConnector = new LLMConnector();
$player = new Player();

$saveSuccess = false;
$saveMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_player'])) {
    try {
        // Save player name
        if (isset($_POST['player_name'])) {
            $player->set('player_name', $_POST['player_name']);
        }
        // Save player info
        if (isset($_POST['appearance'])) {
            $player->set('appearance', $_POST['appearance']);
        }
        if (isset($_POST['bio'])) {
            $player->set('bio', $_POST['bio']);
        }
        $bioKnownByAll = (isset($_POST['bio_known_by_all']) && $_POST['bio_known_by_all'] === 'true') ? 'true' : 'false';
        $player->set('bio_known_by_all', $bioKnownByAll);
        if (isset($_POST['speech_style'])) {
            $player->set('speech_style', $_POST['speech_style']);
        }
        if (isset($_POST['core_connector_player'])) {
            chimSetGeneralSetting(
                'CORE_CONNECTOR_PLAYER',
                trim(strval($_POST['core_connector_player'])),
                chimGetSchemaDescription('CORE_CONNECTOR_PLAYER')
            );
        }
        $player->set('diary_enabled', isset($_POST['diary_enabled']) && $_POST['diary_enabled'] === '1' ? '1' : '0');
        $player->set('auto_diary_enabled', isset($_POST['auto_diary_enabled']) && $_POST['auto_diary_enabled'] === '1' ? '1' : '0');
        $player->set('auto_diary_wait_enabled', isset($_POST['auto_diary_wait_enabled']) && $_POST['auto_diary_wait_enabled'] === '1' ? '1' : '0');
        $player->set('tts_connector_id', trim(strval($_POST['tts_connector_id'] ?? '')));
        $player->set('tts_voice_override', trim(strval($_POST['tts_voice_override'] ?? '')));
        $player->set('tts_voice_id_override', trim(strval($_POST['tts_voice_id_override'] ?? '')));
        $player->set('tts_language_override', trim(strval($_POST['tts_language_override'] ?? '')));
        $player->set('tts_elevenlabs_model_id', trim(strval($_POST['tts_elevenlabs_model_id'] ?? '')));
        $player->set('tts_elevenlabs_speed', trim(strval($_POST['tts_elevenlabs_speed'] ?? '')));
        $player->set('tts_elevenlabs_stability', trim(strval($_POST['tts_elevenlabs_stability'] ?? '')));
        $player->set('tts_elevenlabs_similarity_boost', trim(strval($_POST['tts_elevenlabs_similarity_boost'] ?? '')));
        $player->set('tts_elevenlabs_style', trim(strval($_POST['tts_elevenlabs_style'] ?? '')));
        $player->set('tts_elevenlabs_use_speaker_boost', trim(strval($_POST['tts_elevenlabs_use_speaker_boost'] ?? '')));
        $player->set('tts_elevenlabs_v3_audio_tags', trim(strval($_POST['tts_elevenlabs_v3_audio_tags'] ?? '')));
        
        // Save any editable stats if provided
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'stat_') === 0) {
                $statKey = substr($key, 5); // Remove 'stat_' prefix
                $player->set($statKey, $value);
            }
        }
        
        $saveSuccess = true;
        $saveMessage = 'Player settings saved successfully!';
    } catch (Exception $e) {
        $saveSuccess = false;
        $saveMessage = 'Error saving player settings: ' . $e->getMessage();
    }
}

// Load all player data
$allPlayerData = $player->getAll();

// Extract main fields
$playerName = $allPlayerData['player_name'] ?? 'Unknown';
$appearance = $allPlayerData['appearance'] ?? '';
$bio = $allPlayerData['bio'] ?? '';
$bioKnownByAll = ($allPlayerData['bio_known_by_all'] ?? 'false') === 'true';
$speechStyle = $allPlayerData['speech_style'] ?? '';
$playerDiaryEnabled = $player->getBool('diary_enabled', false);
$playerAutoDiaryEnabled = $player->getBool('auto_diary_enabled', false);
$playerAutoDiaryWaitEnabled = $player->getBool('auto_diary_wait_enabled', false);
$playerTtsConnectorId = trim(strval($allPlayerData['tts_connector_id'] ?? ''));
$playerTtsVoiceId = strval($allPlayerData['tts_voice_override'] ?? '');
$playerTtsVoiceIdOverride = strval($allPlayerData['tts_voice_id_override'] ?? '');
$playerTtsLanguageOverride = strval($allPlayerData['tts_language_override'] ?? '');
$playerTtsElevenModelId = strval($allPlayerData['tts_elevenlabs_model_id'] ?? '');
$playerTtsElevenSpeed = strval($allPlayerData['tts_elevenlabs_speed'] ?? '');
$playerTtsElevenStability = strval($allPlayerData['tts_elevenlabs_stability'] ?? '');
$playerTtsElevenSimilarityBoost = strval($allPlayerData['tts_elevenlabs_similarity_boost'] ?? '');
$playerTtsElevenStyle = strval($allPlayerData['tts_elevenlabs_style'] ?? '');
$playerTtsElevenUseSpeakerBoost = strval($allPlayerData['tts_elevenlabs_use_speaker_boost'] ?? '');
$playerTtsElevenV3AudioTags = strval($allPlayerData['tts_elevenlabs_v3_audio_tags'] ?? '');
$playerRespeechConnectorId = trim(strval(chimGetGeneralSetting('CORE_CONNECTOR_PLAYER', strval($GLOBALS['CORE_CONNECTOR_PLAYER'] ?? ''))));
$ttsConnectorRows = $ttsConnector->readAll();
$llmConnectorRows = $llmConnector->readAll();
$playerDiaryConnectorInfo = chimResolvePlayerDiaryConnectorFromDefaultProfile();
$playerDiaryConnectorLabel = trim(strval($playerDiaryConnectorInfo['connector_label'] ?? 'Not configured'));
$playerDiaryProfileLabel = trim(strval($playerDiaryConnectorInfo['profile_label'] ?? 'Default Profile'));
$playerDiaryConnectorError = trim(strval($playerDiaryConnectorInfo['error'] ?? ''));
$ttsConnectorMeta = [];
foreach ($ttsConnectorRows as $row) {
    $ttsConnectorMeta[strval($row['id'] ?? '')] = [
        'driver' => strval($row['driver'] ?? ''),
        'label' => strval($row['label'] ?? ''),
    ];
}

if ($bio === '' && !empty(trim((string)($GLOBALS["PLAYER_BIOS"] ?? '')))) {
    $bio = trim((string)$GLOBALS["PLAYER_BIOS"]);
}

if ($bio === '') {
    $legacyBioRow = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_BIOS' LIMIT 1");
    $bio = trim((string)($legacyBioRow['value'] ?? ''));
}

if ($bio === '' && !empty(trim((string)($GLOBALS["PLAYER_BIOS"] ?? '')))) {
    $bio = trim((string)$GLOBALS["PLAYER_BIOS"]);
}

if ($bio === '') {
    $legacyBioRow = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_BIOS' LIMIT 1");
    $bio = trim((string)($legacyBioRow['value'] ?? ''));
}

// Load JSON data (equipment, inventory, skills, stats)
$equipment = $player->getJson('equipment') ?? [];
$inventory = $player->getJson('inventory') ?? [];
$skills = $player->getJson('skills') ?? [];
$stats = $player->getJson('stats') ?? [];

// Organize Skyrim stats into categories
$statCategories = [
    'Core Stats' => [
        'Days Passed', 'Hours Slept', 'Hours Waited'
    ],
    'Exploration' => [
        'Locations Discovered', 'Dungeons Cleared', 'Standing Stones Found'
    ],
    'Economy' => [
        'Gold Found', 'Most Gold Carried', 'Chests Looted',
        'Barters', 'Stores Invested In'
    ],
    'Character Development' => [
        'Skill Increases', 'Skill Books Read', 'Training Sessions', 'Books Read'
    ],
    'Lifestyle' => [
        'Food Eaten', 'Horses Owned', 'Houses Owned'
    ],
    'Social' => [
        'Persuasions', 'Bribes', 'Intimidations'
    ],
    'Supernatural' => [
        'Werewolf Transformations', 'Days As Werewolf',
        'Necks Bitten', 'Days As Vampire', 'Diseases Contracted'
    ],
    'Quests - Main' => [
        'Quests Completed', 'Main Quests Completed', 'Side Quests Completed',
        'Misc Objectives Completed', 'Questlines Completed'
    ],
    'Quests - Factions' => [
        'The Companions Quests Completed',
        'College of Winterhold Quests Completed',
        'Thieves\' Guild Quests Completed',
        'Thieves\' Guild Special Jobs Completed',
        'The Dark Brotherhood Quests Completed',
        'Dark Brotherhood Contracts Completed',
        'Bard\'s College Quests Completed',
        'Blades Quests Completed'
    ],
    'Quests - Political' => [
        'Civil War Quests Completed',
        'Imperial Legion Quests Completed',
        'Stormcloaks Quests Completed',
        'Forsworn Quests Completed'
    ],
    'Quests - DLC' => [
        'Daedric Quests Completed',
        'Dragonborn Quests Completed DB',
        'Dawnguard Quests Completed DG'
    ],
    'Combat' => [
        'Mauls'
    ]
];

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$isEmbed) {
    require_once(__DIR__."/../profile_loader.php");
    $TITLE = "Player Management";
    ob_start();
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
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
        padding-top: <?php echo $isEmbed ? '10px' : '80px'; ?>;
        padding-bottom: 24px;
        padding-left: 5%;
        padding-right: 5%;
        /*width: 100%;*/
        margin: 0;
        display: flex;
        justify-content: center;
    }
    
    .page-container {
        width: 100%;
        max-width: 1400px;
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

    /* Header Styling - compact inline row, see .chim-page-head in chim-theme.css */

    /* Content Layout */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .content-grid.two-col {
        grid-template-columns: 1fr 1fr;
    }

    .content-grid.player-overview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .content-grid.player-overview-grid .player-bio-section {
        order: 3;
    }

    .content-grid.player-overview-grid .player-tts-section {
        order: 4;
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

    .content-section h2.section-title-with-status {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .section-title-text {
        font-family: 'MagicCards', serif !important;
        color: inherit;
        text-shadow: inherit;
        word-spacing: inherit;
    }

    .section-status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: 'MagicCards', serif;
        font-size: 0.62em;
        font-weight: 400;
        letter-spacing: 0;
        text-transform: none;
        word-spacing: 4px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.35);
        color: #d9d9d9;
        white-space: nowrap;
    }

    #player_tts_status_text {
        font-family: 'MagicCards', serif !important;
    }

    .section-status-indicator .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #c84b4b;
        box-shadow: 0 0 0 2px rgba(200, 75, 75, 0.2);
        flex: 0 0 auto;
    }

    .section-status-indicator.status-enabled {
        color: #8fd0a6;
    }

    .section-status-indicator.status-enabled .status-dot {
        background: #2d8a57;
        box-shadow: 0 0 0 2px rgba(45, 138, 87, 0.22);
    }

    .section-status-indicator.status-disabled {
        color: #d98d8d;
    }

    .section-status-indicator.status-disabled .status-dot {
        background: #c84b4b;
        box-shadow: 0 0 0 2px rgba(200, 75, 75, 0.2);
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    .content-grid + .full-width-section,
    .full-width-section + .content-grid,
    .full-width-section + .full-width-section,
    .content-grid + .content-grid {
        margin-top: 20px;
    }

    .full-width-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 18px;
        font-size: 1.5em;
        text-align: center;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
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
    .content-section input[type="number"],
    .content-section select,
    .content-section textarea {
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
    .content-section input[type="number"]:focus,
    .content-section select:focus,
    .content-section textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }

    .content-section textarea { 
        min-height: 100px; 
        font-family: inherit; 
        resize: vertical; 
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

    .status-field {
        margin-top: 14px;
        margin-bottom: 14px;
    }

    .status-field-label {
        display: block;
        font-size: 13px;
        color: rgb(242, 124, 17);
        font-weight: 600;
        margin-bottom: 6px;
    }

    .status-field-value {
        color: #e9efff;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
    }

    .status-field-value.warning {
        color: #f1c27d;
    }

    .status-field-source {
        color: #999;
        font-size: 12px;
        line-height: 1.4;
        margin-top: 2px;
    }

    .toggle-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: rgba(26, 26, 26, 0.6);
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        margin-top: 0;
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

    .player-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .player-actions .btn-save {
        margin-bottom: 0;
    }

    .btn-portable {
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
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .btn-portable:hover:not(:disabled) {
        background: #414141;
        border-color: rgba(242, 124, 17, 0.65);
        color: #fff;
        text-decoration: none;
    }

    .btn-portable:disabled {
        opacity: 0.6;
        cursor: wait;
    }

    .btn-ai-generate {
        background-color: #1b4f8c;
        color: #fff;
        border: 1px solid rgba(120, 170, 235, 0.35);
        border-radius: 8px;
        padding: 10px 16px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.2px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255, 255, 255, 0.1);
    }

    .btn-ai-generate:hover:not(:disabled) {
        background-color: #2463ab;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25),
                    inset 0 1px rgba(255, 255, 255, 0.15);
    }

    .btn-ai-generate:disabled {
        opacity: 0.65;
        cursor: not-allowed;
        transform: none;
    }

    .speech-style-tools {
        margin-top: 10px;
        display: grid;
        gap: 8px;
    }

    .player-provider-panel {
        margin-top: 14px;
        padding: 14px;
        border-radius: 8px;
        border: 1px solid rgba(242, 124, 17, 0.18);
        background: rgba(18, 18, 18, 0.42);
    }

    .player-provider-panel h3 {
        margin: 0 0 10px;
        color: #f3d6a8;
        font-size: 1em;
        font-weight: 700;
    }

    .player-provider-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .speech-style-tools label {
        margin-top: 4px;
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        font-size: 1.1em;
        word-spacing: 4px;
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

    /* Stats Grid */
    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(2, 1fr);
        gap: 12px; 
    }

    .stat-card { 
        padding: 14px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 8px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.02);
    }

    .stat-card:hover {
        border-color: #3a3a3a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255,255,255,0.03);
    }

    .stat-card-title { 
        font-size: 11px; 
        color: #888; 
        text-transform: uppercase; 
        margin-bottom: 8px;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .stat-card-value { 
        font-size: 20px; 
        color: #fff; 
        font-weight: 600; 
    }

    .stat-bar-container { 
        width: 100%; 
        height: 7px;
        background: rgba(20, 20, 20, 0.8);
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .stat-bar { 
        height: 100%; 
        background: linear-gradient(90deg, #207a4a, #2aa65e); 
        border-radius: 3px; 
        transition: width 0.3s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.2);
    }

    .stat-bar.health { 
        background: linear-gradient(90deg, #c03, #e04);
    }
    
    .stat-bar.magicka { 
        background: linear-gradient(90deg, #2070c0, #3090e0);
    }
    
    .stat-bar.stamina { 
        background: linear-gradient(90deg, #20a020, #30c030);
    }

    /* Equipment Grid */
    .equipment-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); 
        gap: 10px;
        max-width: 900px;
        margin: 0 auto;
    }

    .equipment-group {
        border: 1px solid #3d4654;
        border-radius: 8px;
        background: #20242b;
        padding: 12px;
        margin: 12px 0;
    }

    .equipment-group-title {
        color: #f27c11;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .equipment-slot { 
        padding: 12px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 8px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.02);
    }

    .equipment-slot:hover {
        border-color: #3a3a3a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255,255,255,0.03);
    }

    .equipment-slot-name { 
        font-size: 11px; 
        color: #888;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .equipment-item-name { 
        font-size: 13px; 
        color: #e9efff;
        font-weight: 500;
    }

    .equipment-empty { 
        font-size: 13px; 
        color: #555;
        font-style: italic; 
    }

    /* Inventory List */
    .inventory-list { 
        max-height: 400px; 
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 4px;
    }
    
    .inventory-list::-webkit-scrollbar {
        width: 8px;
    }

    .inventory-list::-webkit-scrollbar-track {
        background: rgba(26, 26, 26, 0.5);
        border-radius: 4px;
    }

    .inventory-list::-webkit-scrollbar-thumb {
        background: #3a3a3a;
        border-radius: 4px;
    }

    .inventory-list::-webkit-scrollbar-thumb:hover {
        background: #4a4a4a;
    }
    
    .inventory-container {
        max-width: 100%;
    }

    .inventory-item { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 10px 12px;
        background: linear-gradient(90deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 6px;
        margin-bottom: 6px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
    }

    .inventory-item:hover {
        border-color: #3a3a3a;
        transform: translateX(4px);
        background: linear-gradient(90deg, rgba(30, 30, 30, 0.95), rgba(24, 24, 24, 0.98));
    }

    .inventory-item-name { 
        font-size: 13px; 
        color: #e9efff;
        font-weight: 500;
    }

    .inventory-item-count { 
        font-size: 12px; 
        color: #8a9bb6; 
        font-weight: 600; 
        background: rgba(20, 20, 20, 0.8);
        padding: 4px 10px;
        border-radius: 4px;
        border: 1px solid rgba(138, 155, 182, 0.2);
    }

    /* Skills Grid */
    .skills-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
        gap: 10px;
        max-width: 900px;
        margin: 0 auto;
    }

    .skill-item { 
        padding: 10px 12px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(20, 20, 20, 0.95));
        border-radius: 8px;
        border: 1px solid #2a2a2a;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px rgba(255,255,255,0.02);
    }

    .skill-item:hover {
        border-color: #3a3a3a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255,255,255,0.03);
    }

    .skill-name { 
        font-size: 12px; 
        color: #888;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .skill-value { 
        font-size: 18px;
        color: #e9efff;
        font-weight: 600; 
    }

    .no-data { 
        padding: 30px 20px;
        text-align: center; 
        color: #666; 
        font-style: italic;
        line-height: 1.6;
    }

    .no-data ul {
        color: #888;
        font-size: 0.95em;
    }

    .no-data strong {
        color: #999;
    }

    .no-data ul {
        text-align: left;
        margin: 10px auto;
        display: inline-block;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .content-grid.two-col {
            grid-template-columns: 1fr;
        }

        .content-grid.player-overview-grid {
            grid-template-columns: 1fr;
        }

        .player-provider-grid {
            grid-template-columns: 1fr;
        }
        
        .content-section {
            padding: 18px;
        }

        .stats-grid,
        .equipment-grid,
        .skills-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .toggle-row {
            padding: 10px 12px;
        }

        .toggle-label {
            font-size: 13px;
        }

        .content-section {
            padding: 15px;
        }

        .stats-grid,
        .equipment-grid,
        .skills-grid {
            grid-template-columns: 1fr;
        }

        .toggle-row + .hint {
            margin-left: 0;
        }
    }
</style>

<?php if ($isEmbed): ?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/chim-theme.css?v=<?php echo filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'chim-theme.css'); ?>">
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 10px; }
</style>
<?php endif; ?>

<main>
    <div class="page-container">
        <div id="toast" class="toast-notification <?php echo (!$saveSuccess && $saveMessage) ? 'error' : ''; ?>">
            <span class="message"><?php echo htmlspecialchars($saveMessage); ?></span>
        </div>

        <script>
        function showToast(message, isError=false, duration=3200) {
            try {
                const toast = document.getElementById('toast');
                if (!toast) return;
                const messageEl = toast.querySelector('.message');
                if (messageEl) messageEl.textContent = message || '';
                toast.classList.toggle('error', !!isError);
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, duration);
            } catch (_e) {}
        }

        async function generatePlayerSpeechStyle() {
            const btn = document.getElementById('generate_speech_style_btn');
            const speechStyleEl = document.getElementById('speech_style');
            const guidanceEl = document.getElementById('speech_style_guidance');
            if (!btn || !speechStyleEl) return;

            const oldLabel = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Generating...';
            showToast('Generating speech style from your recent dialogue...', false, 8000);

            try {
                const payload = {
                    current_speech_style: speechStyleEl.value || '',
                    player_guidance: guidanceEl ? (guidanceEl.value || '') : ''
                };

                const response = await fetch('<?php echo $webRoot; ?>/ui/cmd/action_player_generate_speech_style.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                let data = null;
                try { data = await response.json(); } catch (_e) {}

                if (!response.ok || !data || data.status !== 'success') {
                    const err = (data && data.message) ? data.message : ('HTTP ' + response.status);
                    throw new Error(err);
                }

                speechStyleEl.value = data.new_value || '';
                showToast(data.message || 'Speech style generated. Save Player Settings to keep it.', false, 4200);
            } catch (error) {
                showToast('Failed to generate speech style: ' + (error.message || 'Unknown error'), true, 5000);
            } finally {
                btn.disabled = false;
                btn.textContent = oldLabel;
            }
        }

        const PLAYER_TTS_CONNECTOR_META = <?php echo json_encode($ttsConnectorMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        function syncPlayerProviderPanels() {
            const connectorSelect = document.getElementById('tts_connector_id');
            const elevenPanel = document.getElementById('player_tts_elevenlabs_panel');
            const statusIndicator = document.getElementById('player_tts_status_indicator');
            const statusText = document.getElementById('player_tts_status_text');
            if (!connectorSelect || !elevenPanel) return;

            const selectedId = connectorSelect.value || '';
            const selectedMeta = PLAYER_TTS_CONNECTOR_META[selectedId] || null;
            const selectedDriver = selectedMeta && selectedMeta.driver ? String(selectedMeta.driver).toLowerCase() : '';
            elevenPanel.style.display = selectedDriver === '11labs' ? 'block' : 'none';

            if (statusIndicator && statusText) {
                const isEnabled = selectedId !== '';
                statusIndicator.classList.toggle('status-enabled', isEnabled);
                statusIndicator.classList.toggle('status-disabled', !isEnabled);
                statusText.textContent = isEnabled ? 'Enabled' : 'Disabled';
                statusIndicator.setAttribute('title', isEnabled ? 'Player TTS is enabled' : 'Player TTS is disabled');
            }
        }
        </script>

        <?php if ($saveSuccess || $saveMessage): ?>
            <script>
            setTimeout(function(){ 
                try{ 
                    showToast(<?php echo json_encode((string)$saveMessage); ?>, <?php echo $saveSuccess ? 'false' : 'true'; ?>, 3000);
                }catch(_e){} 
            }, 50);
            </script>
        <?php endif; ?>

        <div class="page-header chim-page-head">
            <h1 class="chim-page-head-title">👤 Player Management</h1>
            <div class="chim-page-head-note">
                <p>Manage your character's information and view in-game statistics</p>
                <p>Changes made here will be used by AI NPCs to understand your character better</p>
            </div>
        </div>

    <form id="player-form" method="post" action="">
        <div class="player-actions">
            <button type="submit" class="btn-save" name="save_player" value="1">Save Player Settings</button>
            <a class="btn-portable" href="<?php echo $webRoot; ?>/ui/cmd/settings_portability.php?scope=player&amp;action=export">&#128228; Export Player</a>
            <button type="button" class="btn-portable" id="import_player_settings_btn">&#128229; Import Player</button>
            <input type="file" id="import_player_settings_file" accept="application/json,.json" hidden>
        </div>

        <div class="content-grid player-overview-grid">
            <!-- Player Info Section -->
            <div class="content-section">
                <h2>🏷️ Player Information</h2>
                <label for="player_name">Player Name</label>
                <input type="text" id="player_name" name="player_name" value="<?php echo htmlspecialchars($playerName); ?>">
                <span class="hint">Your character's name.</span>
            </div>

            <!-- Appearance Section -->
            <div class="content-section">
                <h2>👤 Player Appearance</h2>
                <label for="appearance">Physical Description</label>
                <textarea id="appearance" name="appearance" placeholder="Describe your character's appearance..."><?php echo htmlspecialchars($appearance); ?></textarea>
                <span class="hint">Physical description of your character used for AI context. NPC will be aware of your appereance.</span>
            </div>

            <!-- Bio Section -->
            <div class="content-section player-bio-section">
                <h2>📜 Player Bio</h2>
                <label for="bio">Character Bio</label>
                <textarea id="bio" name="bio" placeholder="Describe your character's background and story..."><?php echo htmlspecialchars($bio); ?></textarea>
                <span class="hint">Backstory and character context.</span>
                <div style="margin-top: 10px;">
                    <input type="hidden" name="bio_known_by_all" value="false">
                    <label for="bio_known_by_all" style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                        <input
                            type="checkbox"
                            id="bio_known_by_all"
                            name="bio_known_by_all"
                            value="true"
                            <?php echo $bioKnownByAll ? 'checked' : ''; ?>
                        >
                        Player Biography Known by All
                    </label>
                </div>
                <span class="hint">If enabled, all NPCs know this bio. If disabled, only The Narrator knows it.</span>
            </div>

            <div class="content-section player-tts-section">
                <h2 class="section-title-with-status">
                    <span class="section-title-text">Player Autochat and TTS</span>
                    <span
                        id="player_tts_status_indicator"
                        class="section-status-indicator <?php echo $playerTtsConnectorId !== '' ? 'status-enabled' : 'status-disabled'; ?>"
                        title="<?php echo $playerTtsConnectorId !== '' ? 'Player TTS is enabled' : 'Player TTS is disabled'; ?>"
                    >
                        <span class="status-dot" aria-hidden="true"></span>
                        <span id="player_tts_status_text"><?php echo $playerTtsConnectorId !== '' ? 'Enabled' : 'Disabled'; ?></span>
                    </span>
                </h2>
                <label for="tts_connector_id">TTS Connector</label>
                <select id="tts_connector_id" name="tts_connector_id">
                    <option value="">Disabled</option>
                    <?php foreach ($ttsConnectorRows as $row): ?>
                        <?php $rowId = strval($row['id'] ?? ''); ?>
                        <option value="<?php echo htmlspecialchars($rowId); ?>" <?php echo ($playerTtsConnectorId === $rowId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(strval($row['label'] ?? ('Connector #' . $rowId))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Select the connector used when Player TTS generates spoken responses.</span>

                <label for="tts_voice_override">VoiceID</label>
                <input type="text" id="tts_voice_override" name="tts_voice_override" value="<?php echo htmlspecialchars($playerTtsVoiceId); ?>" placeholder="TheNarrator">
                <span class="hint">Dedicated voice identifier used for Player TTS.</span>

                <div id="player_tts_elevenlabs_panel" class="player-provider-panel" style="display:none;">
                    <h3>ElevenLabs Player Overrides</h3>
                    <span class="hint">Only used when the selected Player TTS connector is ElevenLabs. Leave a field blank to inherit the connector default.</span>
                    <div class="player-provider-grid">
                        <div>
                            <label for="tts_elevenlabs_model_id">Model ID</label>
                            <input type="text" id="tts_elevenlabs_model_id" name="tts_elevenlabs_model_id" value="<?php echo htmlspecialchars($playerTtsElevenModelId); ?>" placeholder="eleven_v3">
                            <span class="hint">Examples: <code>eleven_multilingual_v2</code>, <code>eleven_v3</code>.</span>
                        </div>
                        <div>
                            <label for="tts_elevenlabs_speed">Speed</label>
                            <input type="number" step="0.05" id="tts_elevenlabs_speed" name="tts_elevenlabs_speed" value="<?php echo htmlspecialchars($playerTtsElevenSpeed); ?>" placeholder="1.0">
                            <span class="hint">Player-only speed override for ElevenLabs.</span>
                        </div>
                        <div>
                            <label for="tts_elevenlabs_stability">Stability</label>
                            <input type="number" step="0.05" id="tts_elevenlabs_stability" name="tts_elevenlabs_stability" value="<?php echo htmlspecialchars($playerTtsElevenStability); ?>" placeholder="0.75">
                        </div>
                        <div>
                            <label for="tts_elevenlabs_similarity_boost">Similarity Boost</label>
                            <input type="number" step="0.05" id="tts_elevenlabs_similarity_boost" name="tts_elevenlabs_similarity_boost" value="<?php echo htmlspecialchars($playerTtsElevenSimilarityBoost); ?>" placeholder="0.75">
                        </div>
                        <div>
                            <label for="tts_elevenlabs_style">Style</label>
                            <input type="number" step="0.05" id="tts_elevenlabs_style" name="tts_elevenlabs_style" value="<?php echo htmlspecialchars($playerTtsElevenStyle); ?>" placeholder="0.0">
                        </div>
                        <div>
                            <label for="tts_elevenlabs_use_speaker_boost">Speaker Boost</label>
                            <select id="tts_elevenlabs_use_speaker_boost" name="tts_elevenlabs_use_speaker_boost">
                                <option value="" <?php echo $playerTtsElevenUseSpeakerBoost === '' ? 'selected' : ''; ?>>Use Connector Default</option>
                                <option value="true" <?php echo $playerTtsElevenUseSpeakerBoost === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="false" <?php echo $playerTtsElevenUseSpeakerBoost === 'false' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                            <span class="hint">Eleven v3 ignores Speaker Boost.</span>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="tts_elevenlabs_v3_audio_tags">V3 Enhancers</label>
                            <textarea id="tts_elevenlabs_v3_audio_tags" name="tts_elevenlabs_v3_audio_tags" placeholder="[whispers] [curious]"><?php echo htmlspecialchars($playerTtsElevenV3AudioTags); ?></textarea>
                            <span class="hint">Prepended to the Player TTS input when the effective model is <code>eleven_v3</code>. Use ElevenLabs-style audio tags here.</span>
                        </div>
                    </div>
                </div>

                <label for="core_connector_player">Player Respeech Connector</label>
                <select id="core_connector_player" name="core_connector_player">
                    <option value="">Disabled</option>
                    <?php foreach ($llmConnectorRows as $row): ?>
                        <?php
                            $rowId = strval($row['id'] ?? '');
                            $label = trim(strval($row['label'] ?? ''));
                            if ($label === '') {
                                $model = trim(strval($row['model'] ?? ''));
                                $driver = trim(strval($row['driver'] ?? ''));
                                $label = $model !== '' ? $model : ($driver !== '' ? $driver : ('Connector #' . $rowId));
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($rowId); ?>" <?php echo ($playerRespeechConnectorId === $rowId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">LLM connector used to rewrite player autochat prompts.</span>

                <label for="speech_style">Player Speech Style</label>
                <textarea id="speech_style" name="speech_style" placeholder="Describe how your character speaks and communicates..."><?php echo htmlspecialchars($speechStyle); ?></textarea>
                <span class="hint">Used for Auto Chat mode and player-style generation prompts.</span>

                <div class="speech-style-tools">
                    <label for="speech_style_guidance">AI Generation</label>
                    <textarea id="speech_style_guidance" placeholder="Optional: mention traits or tone to prioritize when generating your speech style paragraph."></textarea>
                    <button id="generate_speech_style_btn" type="button" class="btn-ai-generate" onclick="generatePlayerSpeechStyle()">AI Generate From Last 200 Inputs</button>
                </div>
                <span class="hint">Reads up to the last 200 player input events and generates a one-paragraph speech style prompt.</span>
            </div>

            <div class="content-section">
                <h2>📙 Player Diary</h2>
                <input type="hidden" name="diary_enabled" value="0">
                <input type="hidden" name="auto_diary_enabled" value="0">
                <input type="hidden" name="auto_diary_wait_enabled" value="0">
                <div class="status-field">
                    <span class="status-field-label">Player Diary Connector</span>
                    <div class="status-field-value <?php echo $playerDiaryConnectorError !== '' ? 'warning' : ''; ?>">
                        <?php echo htmlspecialchars($playerDiaryConnectorLabel); ?>
                    </div>
                    <div class="status-field-source">
                        Pulled from the Diary connector on the default profile: <?php echo htmlspecialchars($playerDiaryProfileLabel); ?>.
                        <?php if ($playerDiaryConnectorError !== ''): ?>
                            <?php echo htmlspecialchars($playerDiaryConnectorError); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <label class="toggle-row">
                    <div class="toggle-switch">
                        <input
                            type="checkbox"
                            id="diary_enabled"
                            name="diary_enabled"
                            value="1"
                            <?php echo $playerDiaryEnabled ? 'checked' : ''; ?>
                        >
                        <span class="toggle-slider"></span>
                    </div>
                    <span class="toggle-label">Enable <?php echo htmlspecialchars($playerName); ?>'s Diary</span>
                </label>
                <span class="hint">Allows <?php echo htmlspecialchars($playerName); ?> to write diary entries. This can be triggered by the Prisma Actions menu or Auto Diary.</span>

                <label class="toggle-row">
                    <div class="toggle-switch">
                        <input
                            type="checkbox"
                            id="auto_diary_enabled"
                            name="auto_diary_enabled"
                            value="1"
                            <?php echo $playerAutoDiaryEnabled ? 'checked' : ''; ?>
                        >
                        <span class="toggle-slider"></span>
                    </div>
                    <span class="toggle-label">Player Auto Diary</span>
                </label>
                <span class="hint">Automatically writes <?php echo htmlspecialchars($playerName); ?>'s diary when sleeping. Requires Player Diary to be enabled.</span>

                <label class="toggle-row">
                    <div class="toggle-switch">
                        <input
                            type="checkbox"
                            id="auto_diary_wait_enabled"
                            name="auto_diary_wait_enabled"
                            value="1"
                            <?php echo $playerAutoDiaryWaitEnabled ? 'checked' : ''; ?>
                        >
                        <span class="toggle-slider"></span>
                    </div>
                    <span class="toggle-label">Player Auto Diary Wait</span>
                </label>
                <span class="hint">Also writes <?php echo htmlspecialchars($playerName); ?>'s diary when waiting. Requires Player Diary and Player Auto Diary to be enabled.</span>

            </div>
        </div>
    </form>

    <!-- Read-only Game Data Section -->
    <div class="full-width-section">
        <h2>📊 Player Statistics</h2>
    </div>

    <div class="content-grid two-col">
        <!-- Inventory Card -->
        <?php if (!empty($inventory)): ?>
        <div class="content-section">
            <h2>Inventory (<?php echo count($inventory); ?> items)</h2>
            <div class="inventory-container">
                <div class="inventory-list">
                    <?php 
                    // Sort inventory by name
                    usort($inventory, function($a, $b) {
                        return strcmp($a['name'] ?? '', $b['name'] ?? '');
                    });
                    foreach ($inventory as $item): 
                    ?>
                    <div class="inventory-item">
                        <span class="inventory-item-name"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Item'); ?></span>
                        <span class="inventory-item-count">×<?php echo intval($item['count'] ?? 1); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="content-section">
            <h2>Inventory</h2>
            <div class="no-data">No inventory data available. Play the game to sync your inventory.</div>
        </div>
        <?php endif; ?>

        <!-- Equipment Card -->
        <?php
        $equipmentGroups = [
            'Vanilla Slots' => chimEquipmentVanillaSlotLabels(),
            'Modded Slots' => chimEquipmentModdedSlotLabels(),
        ];

        $hasEquipment = false;
        foreach ($equipmentGroups as $equipmentSlots) {
            foreach ($equipmentSlots as $slot => $label) {
                $itemName = isset($equipment[$slot]) && !empty($equipment[$slot]) ? $equipment[$slot] : null;
                if ($itemName) {
                    $hasEquipment = true;
                    break 2;
                }
            }
        }
        ?>
        <div class="content-section">
            <h2>Equipment</h2>
            <?php if (!empty($equipment)): ?>
                <?php if ($hasEquipment): ?>
                    <?php foreach ($equipmentGroups as $groupLabel => $equipmentSlots): ?>
                        <div class="equipment-group">
                            <div class="equipment-group-title"><?php echo htmlspecialchars($groupLabel); ?></div>
                            <div class="equipment-grid">
                                <?php foreach ($equipmentSlots as $slot => $label):
                                    $itemName = isset($equipment[$slot]) && !empty($equipment[$slot]) ? $equipment[$slot] : null;
                                ?>
                                <div class="equipment-slot">
                                    <div class="equipment-slot-name"><?php echo htmlspecialchars($label); ?></div>
                                    <?php if ($itemName): ?>
                                        <div class="equipment-item-name"><?php echo htmlspecialchars($itemName); ?></div>
                                    <?php else: ?>
                                        <div class="equipment-empty">Empty</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <p><strong>No equipment currently equipped.</strong></p>
                        <p>If you have items equipped in-game but they are not showing here:</p>
                        <ul>
                            <li>Make sure you are in-game (not in a menu)</li>
                            <li>Talk to any NPC to trigger a sync</li>
                            <li>Or wait a few seconds for auto-sync</li>
                            <li>Then refresh this page</li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">No equipment data available. Play the game to sync your equipment.</div>
            <?php endif; ?>
        </div>

        <!-- Stats Card -->
        <?php if (!empty($stats)): ?>
        <div class="content-section">
            <h2>Character Stats</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-title">Level</div>
                    <div class="stat-card-value"><?php echo intval($stats['level'] ?? 1); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Health</div>
                    <div class="stat-card-value"><?php 
                        $hp = floatval($stats['health'] ?? 0);
                        $hpMax = floatval($stats['health_max'] ?? 1);
                        echo round($hp) . ' / ' . round($hpMax);
                    ?></div>
                    <div class="stat-bar-container">
                        <div class="stat-bar health" style="width: <?php echo min(100, ($hp / max(1, $hpMax)) * 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Magicka</div>
                    <div class="stat-card-value"><?php 
                        $mp = floatval($stats['magicka'] ?? 0);
                        $mpMax = floatval($stats['magicka_max'] ?? 1);
                        echo round($mp) . ' / ' . round($mpMax);
                    ?></div>
                    <div class="stat-bar-container">
                        <div class="stat-bar magicka" style="width: <?php echo min(100, ($mp / max(1, $mpMax)) * 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Stamina</div>
                    <div class="stat-card-value"><?php 
                        $sp = floatval($stats['stamina'] ?? 0);
                        $spMax = floatval($stats['stamina_max'] ?? 1);
                        echo round($sp) . ' / ' . round($spMax);
                    ?></div>
                    <div class="stat-bar-container">
                        <div class="stat-bar stamina" style="width: <?php echo min(100, ($sp / max(1, $spMax)) * 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- Skills Section -->
    <?php if (!empty($skills)): ?>
    <div class="content-section full-width-section">
        <h2>⭐ Skills</h2>
            <div class="skills-grid">
                <?php 
                // Sort skills by value descending
                arsort($skills);
                foreach ($skills as $skillName => $skillValue): 
                    $displayName = ucwords(str_replace('_', ' ', $skillName));
                ?>
                <div class="skill-item">
                    <div class="skill-name"><?php echo htmlspecialchars($displayName); ?></div>
                    <div class="skill-value"><?php echo round($skillValue); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
    </div>
    <?php endif; ?>
    </div>
</main>

<script src="<?php echo $webRoot; ?>/ui/js/settings-portability.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'settings-portability.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    syncPlayerProviderPanels();
    const connectorSelect = document.getElementById('tts_connector_id');
    if (connectorSelect) {
        connectorSelect.addEventListener('change', syncPlayerProviderPanels);
    }
    if (typeof window.chimInitSettingsImport === 'function') {
        window.chimInitSettingsImport({
            scope: 'player',
            endpoint: <?php echo json_encode($webRoot . '/ui/cmd/settings_portability.php'); ?>,
            importButtonId: 'import_player_settings_btn',
            fileInputId: 'import_player_settings_file'
        });
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
