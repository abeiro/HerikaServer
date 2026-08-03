<?php

class HeldItems
{
    private const HAND_STATE_KEY = 'player_held_item_state';
    private const LEGACY_HAND_STATE_KEY = 'vr_player_hand_state';
    private const DEFAULT_STATE_TTL_SECONDS = 600;

    public static function processEventRequest(array $gameRequest): ?array
    {
        if (!in_array(($gameRequest[0] ?? ''), ['ext_held_item_raw', 'ext_vr_item_raw'], true)) {
            return null;
        }

        $rawData = trim((string)($gameRequest[3] ?? ''));
        if ($rawData === '') {
            self::logMessage('[HeldItems] Empty held item event');
            return null;
        }

        $event = self::parseRawEvent($rawData);
        if ($event === null) {
            self::logMessage('[HeldItems] Invalid held item event: ' . $rawData);
            return null;
        }

        $playerName = self::playerName();
        $handLabel = self::handLabel($event['hand']);

        if ($event['action'] === 'pickup') {
            self::updateHandState($event['hand'], $event['item'], $event['refid']);
        } else {
            $heldItem = self::heldItemForHand($event['hand']);
            if ($event['refid'] === null && $heldItem !== null) {
                $event['refid'] = $heldItem['refid'];
            }
            if (($event['item'] === '' || $event['item'] === 'something') && $heldItem !== null) {
                $event['item'] = $heldItem['name'];
            }
        }

        $itemForPrompt = self::formatHeldIdentifier($event['item'], $event['refid']);

        if ($event['action'] === 'pickup') {
            $message = $event['hand'] === 'both'
                ? "{$playerName} picked up {$itemForPrompt}."
                : "{$playerName} picked up {$itemForPrompt} with their {$handLabel}.";
            $promptKey = 'ext_held_item_pickup';
        } else {
            $message = $event['hand'] === 'both'
                ? "{$playerName} stopped holding {$itemForPrompt}."
                : "{$playerName} stopped holding {$itemForPrompt} with their {$handLabel}.";
            $promptKey = 'ext_held_item_drop';
            self::clearHand($event['hand']);
        }

        $processedRequest = $gameRequest;
        $processedRequest[0] = $promptKey;
        $processedRequest[3] = "<HELD_ITEM>\n{$message}\n</HELD_ITEM>";

        self::logMessage("[HeldItems] {$event['action']} {$event['item']} {$event['hand']}");
        return $processedRequest;
    }

    public static function getHeldItemsContext(): string
    {
        $state = self::getHandState();
        $lines = [];
        if (!empty($state['both'])) {
            $lines[] = "- Both: " . self::formatHeldStateValue($state['both']);
        }

        if (!empty($state['left'])) {
            $lines[] = "- Left: " . self::formatHeldStateValue($state['left']);
        }

        if (!empty($state['right'])) {
            $lines[] = "- Right: " . self::formatHeldStateValue($state['right']);
        }

        if (empty($lines)) {
            return '';
        }

        return "<held_items>\n# PLAYER HELD ITEMS\nFormat: Hand: RefID:ItemName\n\n"
            . implode("\n", $lines)
            . "\n</held_items>";
    }

    public static function getHandState(): array
    {
        if (!isset($GLOBALS['db'])) {
            return self::emptyState();
        }

        $row = self::fetchStateRow(self::HAND_STATE_KEY);
        if (!$row) {
            $row = self::fetchStateRow(self::LEGACY_HAND_STATE_KEY);
        }
        $rawValue = is_array($row) ? ($row['value'] ?? '') : ($row ?? '');

        if ($rawValue === '') {
            return self::emptyState();
        }

        $state = json_decode((string)$rawValue, true);
        if (!is_array($state)) {
            return self::emptyState();
        }

        $state = array_merge(self::emptyState(), $state);
        if (self::isStale($state)) {
            self::clearState();
            return self::emptyState();
        }

        return $state;
    }

    public static function clearState(): void
    {
        if (!isset($GLOBALS['db'])) {
            return;
        }

        self::saveState(self::emptyState());
    }

    public static function hasHeldItems(): bool
    {
        $heldState = self::getHandState();
        foreach (['both', 'left', 'right'] as $hand) {
            if (self::normalizeHeldStateValue($heldState[$hand] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    public static function resolveHeldIdentifier(string $identifier): ?string
    {
        $identifier = trim($identifier, " \t\n\r\0\x0B`");
        if (!preg_match('/^(0x[0-9a-f]{1,8})\s*:(.+)$/i', $identifier, $matches)) {
            return null;
        }

        $requestedRefId = self::normalizeRefId($matches[1]);
        if ($requestedRefId === null) {
            return null;
        }

        $heldState = self::getHandState();
        foreach (['both', 'left', 'right'] as $hand) {
            $heldItem = self::normalizeHeldStateValue($heldState[$hand] ?? null);
            if ($heldItem !== null && $heldItem['refid'] === $requestedRefId) {
                return $requestedRefId . ':' . $heldItem['name'];
            }
        }

        return null;
    }

    private static function parseRawEvent(string $rawData): ?array
    {
        $parts = explode('^', $rawData, 4);
        if (count($parts) < 3) {
            return null;
        }

        $item = self::sanitizeItemName($parts[0]);
        $action = strtolower(trim($parts[1]));
        $hand = strtolower(trim($parts[2]));
        $refid = self::normalizeRefId($parts[3] ?? null);

        if ($item === '') {
            $item = 'something';
        }

        if (!in_array($action, ['pickup', 'drop'], true)) {
            return null;
        }

        if (!in_array($hand, ['left', 'right', 'both'], true)) {
            return null;
        }

        return [
            'item' => $item,
            'action' => $action,
            'hand' => $hand,
            'refid' => $refid,
        ];
    }

    private static function updateHandState(string $hand, ?string $itemName, ?string $refid = null): void
    {
        $state = self::getHandState();
        $state[$hand] = $itemName === null ? null : [
            'name' => $itemName,
            'refid' => $refid,
        ];
        $state['updated_at'] = time();
        self::saveState($state);
    }

    private static function clearHand(string $hand): void
    {
        self::updateHandState($hand, null, null);
    }

    private static function saveState(array $state): void
    {
        if (!isset($GLOBALS['db'])) {
            return;
        }

        $state = array_merge(self::emptyState(), $state);
        $state['updated_at'] = intval($state['updated_at'] ?? time());

        $GLOBALS['db']->upsertRowOnConflict(
            'conf_opts',
            [
                'id' => self::HAND_STATE_KEY,
                'value' => json_encode($state),
            ],
            'id'
        );
    }

    private static function emptyState(): array
    {
        return [
            'left' => null,
            'right' => null,
            'both' => null,
            'updated_at' => 0,
        ];
    }

    private static function fetchStateRow(string $stateKey)
    {
        if (!isset($GLOBALS['db'])) {
            return null;
        }

        $key = $GLOBALS['db']->escape($stateKey);
        return $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id = '{$key}' LIMIT 1");
    }

    private static function heldItemForHand(string $hand): ?array
    {
        $state = self::getHandState();
        return self::normalizeHeldStateValue($state[$hand] ?? null);
    }

    private static function normalizeHeldStateValue($value): ?array
    {
        if (is_string($value)) {
            $name = self::sanitizeItemName($value);
            return $name === '' ? null : ['name' => $name, 'refid' => null];
        }

        if (!is_array($value)) {
            return null;
        }

        $name = self::sanitizeItemName((string) ($value['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'refid' => self::normalizeRefId($value['refid'] ?? null),
        ];
    }

    private static function formatHeldStateValue($value): string
    {
        $item = self::normalizeHeldStateValue($value);
        if ($item === null) {
            return 'Unknown item';
        }

        return self::formatHeldIdentifier($item['name'], $item['refid']);
    }

    private static function formatHeldIdentifier(string $itemName, ?string $refid): string
    {
        $safeName = str_replace('`', '&#96;', self::escapeXmlText(self::sanitizeItemName($itemName)));
        return $refid !== null ? "`{$refid}:{$safeName}`" : $safeName;
    }

    private static function normalizeRefId($value): ?string
    {
        $refid = trim((string) $value);
        if ($refid === '') {
            return null;
        }

        if (stripos($refid, '0x') === 0) {
            $refid = substr($refid, 2);
        }

        if (!preg_match('/^[0-9a-f]{1,8}$/i', $refid)) {
            return null;
        }

        return '0x' . strtoupper(str_pad($refid, 8, '0', STR_PAD_LEFT));
    }

    private static function handLabel(string $hand): string
    {
        if ($hand === 'left') {
            return 'left hand';
        }

        if ($hand === 'right') {
            return 'right hand';
        }

        return 'hands';
    }

    private static function isStale(array $state): bool
    {
        if (empty($state['left']) && empty($state['right']) && empty($state['both'])) {
            return false;
        }

        $updatedAt = intval($state['updated_at'] ?? 0);
        if ($updatedAt <= 0) {
            return true;
        }

        $ttl = intval($GLOBALS['HELD_ITEM_TTL_SECONDS'] ?? $GLOBALS['VR_HELD_ITEM_TTL_SECONDS'] ?? self::DEFAULT_STATE_TTL_SECONDS);
        if ($ttl <= 0) {
            return false;
        }

        return (time() - $updatedAt) > $ttl;
    }

    private static function sanitizeItemName(string $itemName): string
    {
        $itemName = str_replace(["\r", "\n", "\t", '^', '|'], ' ', $itemName);
        return trim(preg_replace('/\s+/', ' ', $itemName) ?? $itemName);
    }

    private static function escapeXmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function playerName(): string
    {
        $playerName = trim((string)($GLOBALS['PLAYER_NAME'] ?? ''));
        return $playerName !== '' ? self::escapeXmlText($playerName) : 'Player';
    }

    private static function logMessage(string $message): void
    {
        if (class_exists('Logger')) {
            Logger::info($message);
        } else {
            error_log($message);
        }
    }
}

class VRItems extends HeldItems
{
}
