<?php

if (!function_exists('chimFormatPlayerDiaryConnectorLabel')) {
    function chimFormatPlayerDiaryConnectorLabel($connectorData, $connectorId = null): string
    {
        if (!is_array($connectorData) || empty($connectorData)) {
            return 'Not configured';
        }

        $label = trim(strval($connectorData['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $model = trim(strval($connectorData['model'] ?? ''));
        $driver = trim(strval($connectorData['driver'] ?? ''));
        if ($model !== '' && $driver !== '') {
            return $model . ' (' . $driver . ')';
        }
        if ($model !== '') {
            return $model;
        }
        if ($driver !== '') {
            return $driver;
        }

        $connectorId = intval($connectorId ?? ($connectorData['id'] ?? 0));
        return $connectorId > 0 ? ('Connector #' . $connectorId) : 'Not configured';
    }
}

if (!function_exists('chimResolvePlayerDiaryConnectorFromDefaultProfile')) {
    function chimResolvePlayerDiaryConnectorFromDefaultProfile(): array
    {
        $result = [
            'profile_data' => null,
            'profile_id' => null,
            'profile_label' => 'Default Profile',
            'profile_source' => 'default_npc',
            'connector_id' => null,
            'connector_data' => null,
            'connector_label' => 'Not configured',
            'error' => '',
        ];

        if (!isset($GLOBALS['db']) || !is_object($GLOBALS['db'])) {
            $result['error'] = 'Database is not available.';
            return $result;
        }

        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "core_profiles.class.php");
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");

        $randomizerPath = __DIR__ . DIRECTORY_SEPARATOR . "llm_randomizer.php";
        if (file_exists($randomizerPath)) {
            require_once($randomizerPath);
        }

        $profileManager = new CoreProfile();
        $profileData = $profileManager->getDefaultNpc();
        if (empty($profileData)) {
            $profileData = $profileManager->getById(1);
            $result['profile_source'] = 'profile_id_1';
        }

        if (empty($profileData) || !is_array($profileData)) {
            $result['error'] = 'No default profile is configured.';
            return $result;
        }

        $profileId = intval($profileData['id'] ?? 0);
        $profileLabel = trim(strval($profileData['label'] ?? ''));
        $result['profile_data'] = $profileData;
        $result['profile_id'] = $profileId > 0 ? $profileId : null;
        $result['profile_label'] = $profileLabel !== '' ? $profileLabel : ($profileId > 0 ? ('Profile #' . $profileId) : 'Default Profile');

        $connectorId = class_exists('LLMRandomizer')
            ? LLMRandomizer::getConnectorIdForField($profileData, "diary_connector_id")
            : ($profileData["diary_connector_id"] ?? null);
        $connectorId = intval($connectorId ?? 0);

        if ($connectorId <= 0) {
            $result['error'] = 'Default profile has no diary connector configured.';
            return $result;
        }

        $connectorManager = new LLMConnector();
        $connectorData = $connectorManager->getById($connectorId);
        if (empty($connectorData) || !is_array($connectorData)) {
            $result['connector_id'] = $connectorId;
            $result['error'] = 'Default profile diary connector was not found.';
            return $result;
        }

        $result['connector_id'] = $connectorId;
        $result['connector_data'] = $connectorData;
        $result['connector_label'] = chimFormatPlayerDiaryConnectorLabel($connectorData, $connectorId);

        return $result;
    }
}

?>
