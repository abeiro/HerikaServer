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

        if ($event['action'] === 'pickup') {
            self::updateHandState($event['hand'], $event['item']);
        } else {
            self::clearHand($event['hand']);
        }

        $playerName = self::playerName();
        $handLabel = self::handLabel($event['hand']);
        $itemForXml = self::escapeXmlText($event['item']);

        if ($event['action'] === 'pickup') {
            $message = $event['hand'] === 'both'
                ? "{$playerName} picked up {$itemForXml}."
                : "{$playerName} picked up {$itemForXml} with their {$handLabel}.";
            $promptKey = 'ext_held_item_pickup';
        } else {
            $message = $event['hand'] === 'both'
                ? "{$playerName} stopped holding {$itemForXml}."
                : "{$playerName} stopped holding {$itemForXml} with their {$handLabel}.";
            $promptKey = 'ext_held_item_drop';
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
        $playerName = self::playerName();

        if (!empty($state['both'])) {
            $lines[] = "{$playerName} is holding " . self::escapeXmlText((string)$state['both']) . ".";
        }

        if (!empty($state['left'])) {
            $lines[] = "{$playerName} is holding " . self::escapeXmlText((string)$state['left']) . " in their left hand.";
        }

        if (!empty($state['right'])) {
            $lines[] = "{$playerName} is holding " . self::escapeXmlText((string)$state['right']) . " in their right hand.";
        }

        if (empty($lines)) {
            return '';
        }

        return "<held_items>\n# PLAYER HELD ITEMS\n## " . implode("\n## ", $lines) . "\n</held_items>";
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

    private static function parseRawEvent(string $rawData): ?array
    {
        $parts = explode('^', $rawData, 3);
        if (count($parts) < 3) {
            return null;
        }

        $item = self::sanitizeItemName($parts[0]);
        $action = strtolower(trim($parts[1]));
        $hand = strtolower(trim($parts[2]));

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
        ];
    }

    private static function updateHandState(string $hand, ?string $itemName): void
    {
        $state = self::getHandState();
        $state[$hand] = $itemName;
        $state['updated_at'] = time();
        self::saveState($state);
    }

    private static function clearHand(string $hand): void
    {
        self::updateHandState($hand, null);
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
