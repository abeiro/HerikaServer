<?php

/**
 * Resolve the connector used by Background Life without requiring the optional
 * global override to be configured.
 *
 * @return array{data: array, source: string}
 */
function chimResolveBackgroundLifeConnector(
    $configuredConnectorId,
    array $currentProfile,
    array $defaultProfile,
    callable $fetchConnector
): array {
    $candidates = [
        'Background Life setting' => $configuredConnectorId,
        'NPC profile primary LLM' => $currentProfile['llm_primary_id'] ?? null,
        'default profile primary LLM' => $defaultProfile['llm_primary_id'] ?? null,
    ];

    $seen = [];
    foreach ($candidates as $source => $candidateId) {
        $connectorId = (int) $candidateId;
        if ($connectorId <= 0 || isset($seen[$connectorId])) {
            continue;
        }

        $seen[$connectorId] = true;
        $connectorData = $fetchConnector($connectorId);
        if (!is_array($connectorData) || trim((string) ($connectorData['driver'] ?? '')) === '') {
            continue;
        }

        return [
            'data' => $connectorData,
            'source' => $source,
        ];
    }

    throw new RuntimeException(
        'Background Life has no usable LLM connector. Configure its connector or assign a primary LLM to the NPC/default profile.'
    );
}
