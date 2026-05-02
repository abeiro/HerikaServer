<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "api_badge.class.php";

class ITTConnector
{
    private $table = "core_itt_connector";

    private static $driverMap = [
        'custom' => 'custom',
        'openai' => 'openai',
        'google_openai' => 'google_openai',
        'openrouter' => 'openrouter',
        'llamacpp' => 'llamacpp',
    ];

    private static $displayNameMap = [
        'custom' => 'Custom',
        'openai' => 'OpenAI',
        'google_openai' => 'Google OpenAI',
        'openrouter' => 'OpenRouter',
        'llamacpp' => 'llama.cpp',
    ];

    private static $apiBadgeLabelMap = [
        'openai' => 'OpenAI',
        'google_openai' => 'Google',
        'openrouter' => 'OpenRouter',
    ];

    private static $localUrlDefaultMap = [
        'llamacpp' => 'http://127.0.0.1:8080',
    ];

    public function create($data)
    {
        $fields = ["driver", "label", "metadata", "api_badge_id", "url"];
        $driver = $data['driver'] ?? '';
        if (array_key_exists('metadata', $data)) {
            $metadata = $this->decodeMetadata($data['metadata']);
            $data['api_badge_id'] = $this->resolveApiBadgeIdForMetadata($driver, $metadata, $data['api_badge_id'] ?? null);
            $data['metadata'] = $this->canonicalJson($metadata);
        }
        $data['url'] = $this->normalizeUrlForDriver($driver, $data['url'] ?? null, $this->decodeMetadata($data['metadata'] ?? '{}'));

        foreach ($data as $k => $v) {
            if (($v === "" || $v === null) && $v !== "0" && $v !== false && $v !== 0) {
                $data[$k] = null;
            }
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        $query = "INSERT INTO {$this->table} (driver, label, metadata, api_badge_id, url) VALUES (" .
            $GLOBALS["db"]->escapeLiteral(strval($filtered['driver'] ?? '')) . ", " .
            $GLOBALS["db"]->escapeLiteral(strval($filtered['label'] ?? '')) . ", " .
            ($filtered['metadata'] === null ? "NULL" : $GLOBALS["db"]->escapeLiteral(strval($filtered['metadata']))) . ", " .
            ($filtered['api_badge_id'] === null ? "NULL" : intval($filtered['api_badge_id'])) . ", " .
            ($filtered['url'] === null ? "NULL" : $GLOBALS["db"]->escapeLiteral(strval($filtered['url']))) .
            ") RETURNING id";
        $row = $GLOBALS["db"]->fetchOne($query);
        return isset($row['id']) ? intval($row['id']) : 0;
    }

    public function readAll()
    {
        return $GLOBALS["db"]->fetchAll("SELECT * FROM {$this->table} ORDER BY LOWER(COALESCE(NULLIF(label,''), driver)) ASC, id ASC");
    }

    public function readOne($id)
    {
        $id = intval($id);
        return $GLOBALS["db"]->fetchOne("SELECT * FROM {$this->table} WHERE id = {$id} LIMIT 1");
    }

    public function getById($id)
    {
        return $this->readOne($id);
    }

    public function update($id, $data)
    {
        $id = intval($id);
        $existing = $this->readOne($id);
        $driver = $data['driver'] ?? ($existing['driver'] ?? '');

        if (array_key_exists('metadata', $data)) {
            $metadata = $this->decodeMetadata($data['metadata']);
            $data['api_badge_id'] = $this->resolveApiBadgeIdForMetadata($driver, $metadata, $data['api_badge_id'] ?? ($existing['api_badge_id'] ?? null));
            $data['metadata'] = $this->canonicalJson($metadata);
        }

        if (!array_key_exists('url', $data)) {
            $data['url'] = $existing['url'] ?? null;
        }
        $data['url'] = $this->normalizeUrlForDriver($driver, $data['url'], $this->decodeMetadata($data['metadata'] ?? ($existing['metadata'] ?? '{}')));

        foreach ($data as $k => $v) {
            if (($v === "" || $v === null) && $v !== "0" && $v !== false && $v !== 0) {
                $data[$k] = null;
            }
        }

        $filtered = array_intersect_key($data, array_flip(["driver", "label", "metadata", "api_badge_id", "url"]));
        $GLOBALS["db"]->updateRow($this->table, $filtered, "id = {$id}");
        return $id;
    }

    public function delete($id)
    {
        return $GLOBALS["db"]->delete($this->table, "id = " . intval($id));
    }

    public function truncate($restart = false, $cascade = false)
    {
        return $GLOBALS["db"]->truncate($this->table, $restart, $cascade);
    }

    public function getLastError()
    {
        return $GLOBALS["db"]->GetLastError();
    }

    public function clone($id)
    {
        $original = $this->readOne($id);
        if (!$original) {
            return 0;
        }

        unset($original['id']);
        $original['label'] = $this->uniqueLabel(trim(strval($original['label'] ?? '')) . ' (Copy)', 0);
        return $this->create($original);
    }

    public function getAllFk($fieldName)
    {
        if ($fieldName !== 'api_badge_id') {
            return [];
        }
        return $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_api_badge ORDER BY LOWER(label) ASC");
    }

    public function getDriverOptions(): array
    {
        $schema = $this->loadRawSchema();
        $values = $schema['ITTFUNCTION']['values'] ?? [];
        if (!is_array($values) || empty($values)) {
            $values = array_keys(self::$driverMap);
        }
        return array_values(array_unique($values));
    }

    public function normalizeDriverValue($driver): string
    {
        return $this->normalizeDriver($driver);
    }

    public function getProviderKeyFromDriver($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        return self::$driverMap[$driver] ?? '';
    }

    public function getDisplayName($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        return self::$displayNameMap[$driver] ?? ($driver !== '' ? $driver : 'Connector');
    }

    public function getProviderFieldSchema($driver): array
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        if ($providerKey === '') {
            return [];
        }

        $schema = $this->loadRawSchema();
        $providerSchema = $schema['ITT'][$providerKey] ?? [];
        return is_array($providerSchema) ? $providerSchema : [];
    }

    public function getProviderTitle($driver): string
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        $schema = $this->getProviderFieldSchema($driver);
        if (!empty($schema['_title'])) {
            return strval($schema['_title']);
        }
        if ($providerKey !== '') {
            return $providerKey;
        }
        return $this->getDisplayName($driver);
    }

    public function driverUsesApiBadge($driver): bool
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        if ($providerKey === '') {
            return false;
        }

        $schema = $this->getProviderFieldSchema($driver);
        if (isset($schema['API_KEY'])) {
            return true;
        }

        return isset(self::$apiBadgeLabelMap[$providerKey]);
    }

    public function getDefaultApiBadgeIdForDriver($driver): int
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        $badgeLabel = self::$apiBadgeLabelMap[$providerKey] ?? '';
        if ($badgeLabel === '') {
            return 0;
        }

        $apiBadge = new ApiBadge();
        $badge = $apiBadge->getByLabel($badgeLabel);
        return $badge ? intval($badge['id'] ?? 0) : 0;
    }

    public function driverSupportsEditableUrl($driver): bool
    {
        $driver = $this->normalizeDriver($driver);
        return $driver === 'llamacpp' || isset(self::$driverMap[$driver]);
    }

    public function getDefaultUrlForDriver($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        if (isset(self::$localUrlDefaultMap[$driver])) {
            return self::$localUrlDefaultMap[$driver];
        }

        $schema = $this->getProviderFieldSchema($driver);
        foreach (['url', 'URL'] as $fieldName) {
            if (isset($schema[$fieldName]['default']) && trim(strval($schema[$fieldName]['default'])) !== '') {
                return trim(strval($schema[$fieldName]['default']));
            }
        }

        return '';
    }

    public function decodeMetadata($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode(strval($raw ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function uniqueLabel(string $base, int $excludeId = 0): string
    {
        $base = trim($base);
        if ($base === '') {
            $base = 'ITT Connector';
        }

        $rows = $this->readAll();
        $used = [];
        foreach ($rows as $row) {
            $id = intval($row['id'] ?? 0);
            if ($excludeId > 0 && $id === $excludeId) {
                continue;
            }
            $used[strtolower(trim(strval($row['label'] ?? '')))] = true;
        }

        if (!isset($used[strtolower($base)])) {
            return $base;
        }

        for ($i = 2; $i < 5000; $i++) {
            $candidate = $base . ' ' . $i;
            if (!isset($used[strtolower($candidate)])) {
                return $candidate;
            }
        }

        return $base . ' ' . time();
    }

    public function buildMetadataFromPostedForm($driver, array $source, array $existingMetadata = []): array
    {
        $schema = $this->getProviderFieldSchema($driver);
        $metadata = $existingMetadata;
        foreach ($schema as $fieldName => $definition) {
            if (!is_array($definition) || $fieldName === '_title') {
                continue;
            }

            $postKey = 'meta__' . $fieldName;
            if (!array_key_exists($postKey, $source) && (($definition['type'] ?? '') !== 'boolean')) {
                continue;
            }

            $type = $definition['type'] ?? 'string';
            if ($type === 'boolean') {
                $metadata[$fieldName] = isset($source[$postKey]) && strval($source[$postKey]) === 'true';
            } elseif ($type === 'integer' || $type === 'int') {
                $raw = trim(strval($source[$postKey] ?? ''));
                $metadata[$fieldName] = ($raw === '') ? 0 : intval($raw);
            } elseif ($type === 'number') {
                $raw = trim(strval($source[$postKey] ?? ''));
                $metadata[$fieldName] = ($raw === '') ? 0 : floatval($raw);
            } elseif ($type === 'selectmultiple') {
                $metadata[$fieldName] = isset($source[$postKey]) && is_array($source[$postKey]) ? array_values($source[$postKey]) : [];
            } else {
                $metadata[$fieldName] = is_array($source[$postKey] ?? null) ? [] : trim(strval($source[$postKey] ?? ''));
            }
        }

        return $metadata;
    }

    public function ensureLegacySelectionFromGlobals(string $label = ''): ?array
    {
        $payload = $this->buildLegacyConnectorPayloadFromGlobals(null, $label);
        if (!$payload) {
            return null;
        }

        $match = $this->findMatchingConnector($payload);
        if ($match) {
            return $match;
        }

        $payload['label'] = $this->uniqueLabel($payload['label'] ?? '', 0);
        $newId = $this->create($payload);
        return $newId > 0 ? $this->getById($newId) : null;
    }

    public function setOldGlobals($currentConnectorData)
    {
        if (!$currentConnectorData) {
            return;
        }

        $driver = $this->normalizeDriver($currentConnectorData['driver'] ?? '');
        if ($driver === '') {
            return;
        }

        $providerKey = $this->getProviderKeyFromDriver($driver);
        $GLOBALS["ITTFUNCTION"] = $driver;
        if (!isset($GLOBALS["ITT"]) || !is_array($GLOBALS["ITT"])) {
            $GLOBALS["ITT"] = [];
        }
        if ($providerKey !== '') {
            $GLOBALS["ITT"][$providerKey] = [];
        }

        $metadata = $this->decodeMetadata($currentConnectorData['metadata'] ?? '{}');
        $resolvedUrl = $this->normalizeUrlForDriver($driver, $currentConnectorData['url'] ?? null, $metadata);
        if ($resolvedUrl !== null && $resolvedUrl !== '') {
            $metadata['url'] = $metadata['url'] ?? $resolvedUrl;
            $metadata['URL'] = $metadata['URL'] ?? $resolvedUrl;
        }

        $apiBadgeId = intval($currentConnectorData['api_badge_id'] ?? 0);
        if ($apiBadgeId > 0) {
            $apiBadge = new ApiBadge();
            $apiKeyData = $apiBadge->getById($apiBadgeId);
            $apiKey = trim(strval($apiKeyData['api_key'] ?? ''));
            if ($apiKey !== '') {
                $metadata['API_KEY'] = $apiKey;
            }
        }

        if ($providerKey !== '') {
            foreach ($metadata as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $GLOBALS["ITT"][$providerKey][$key] = $value;
            }
        }
    }

    private function normalizeDriver($driver): string
    {
        return strtolower(trim(strval($driver)));
    }

    private function buildLegacyConnectorPayloadFromGlobals($driver = null, string $label = ''): ?array
    {
        $driver = $this->normalizeDriver($driver ?? ($GLOBALS["ITTFUNCTION"] ?? ''));
        if ($driver === '') {
            return null;
        }

        $providerKey = $this->getProviderKeyFromDriver($driver);
        $metadata = [];
        if ($providerKey !== '' && isset($GLOBALS["ITT"][$providerKey]) && is_array($GLOBALS["ITT"][$providerKey])) {
            $metadata = $GLOBALS["ITT"][$providerKey];
        }

        $metadata = $this->decodeMetadata($metadata);
        $url = $this->normalizeUrlForDriver($driver, null, $metadata);
        $apiBadgeId = $this->resolveApiBadgeIdForMetadata($driver, $metadata, null);

        if ($label === '') {
            $label = 'Global ' . $this->getDisplayName($driver);
        }

        return [
            'driver' => $driver,
            'label' => trim($label),
            'metadata' => $this->canonicalJson($metadata),
            'api_badge_id' => $apiBadgeId,
            'url' => $url,
        ];
    }

    private function loadRawSchema(): array
    {
        static $schema = null;
        if (is_array($schema)) {
            return $schema;
        }

        $schemaPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf_schema.json";
        $decoded = @json_decode(@file_get_contents($schemaPath), true);
        $schema = is_array($decoded) ? $decoded : [];
        return $schema;
    }

    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }

    private function canonicalJson($value): string
    {
        return json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function findMatchingConnector(array $payload): ?array
    {
        foreach ($this->readAll() as $row) {
            if ($this->normalizeDriver($row['driver'] ?? '') !== $this->normalizeDriver($payload['driver'] ?? '')) {
                continue;
            }
            if (trim(strval($row['url'] ?? '')) !== trim(strval($payload['url'] ?? ''))) {
                continue;
            }
            if (intval($row['api_badge_id'] ?? 0) !== intval($payload['api_badge_id'] ?? 0)) {
                continue;
            }

            $rowMetadata = $this->canonicalJson($this->decodeMetadata($row['metadata'] ?? '{}'));
            if ($rowMetadata === strval($payload['metadata'] ?? '{}')) {
                return $row;
            }
        }

        return null;
    }

    private function resolveApiBadgeIdForMetadata($driver, array &$metadata, $existingApiBadgeId = null): ?int
    {
        $driver = $this->normalizeDriver($driver);
        if (!$this->driverUsesApiBadge($driver)) {
            return null;
        }

        $currentId = intval($existingApiBadgeId ?? 0);
        if ($currentId > 0) {
            return $currentId;
        }

        $providerKey = $this->getProviderKeyFromDriver($driver);
        $badgeLabel = self::$apiBadgeLabelMap[$providerKey] ?? '';
        $apiKey = trim(strval($metadata['API_KEY'] ?? ''));
        $apiBadge = new ApiBadge();
        $badge = $badgeLabel !== '' ? $apiBadge->getByLabel($badgeLabel) : null;

        if (!$badge && $apiKey !== '' && $badgeLabel !== '') {
            $apiBadge->create([
                'label' => $badgeLabel,
                'api_key' => $apiKey,
            ]);
            $badge = $apiBadge->getByLabel($badgeLabel);
        } elseif ($badge && trim(strval($badge['api_key'] ?? '')) === '' && $apiKey !== '') {
            $apiBadge->update(intval($badge['id'] ?? 0), ['api_key' => $apiKey]);
            $badge = $apiBadge->getById(intval($badge['id'] ?? 0));
        }

        if ($badge && intval($badge['id'] ?? 0) > 0) {
            unset($metadata['API_KEY']);
            return intval($badge['id']);
        }

        $defaultId = $this->getDefaultApiBadgeIdForDriver($driver);
        return $defaultId > 0 ? $defaultId : null;
    }

    private function normalizeUrlForDriver($driver, $url, array $metadata = []): ?string
    {
        $driver = $this->normalizeDriver($driver);
        if (!$this->driverSupportsEditableUrl($driver)) {
            return null;
        }

        $candidate = trim(strval($url ?? ''));
        if ($candidate === '') {
            foreach (['url', 'URL', 'endpoint'] as $key) {
                if (isset($metadata[$key]) && trim(strval($metadata[$key])) !== '') {
                    $candidate = trim(strval($metadata[$key]));
                    break;
                }
            }
        }
        if ($candidate === '') {
            $candidate = $this->getDefaultUrlForDriver($driver);
        }

        return $candidate !== '' ? $candidate : null;
    }
}

?>
