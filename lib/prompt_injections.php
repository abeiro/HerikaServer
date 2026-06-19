<?php

function chimNormalizePromptInjectionKey(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_.:-]+/', '_', $value);
    return trim((string) $value, '_');
}

function chimRegisterPromptInjection(string $slot, string $id, $content, int $priority = 100): bool
{
    $slot = chimNormalizePromptInjectionKey($slot);
    $id = chimNormalizePromptInjectionKey($id);
    if ($slot === '' || $id === '') {
        return false;
    }

    if (!isset($GLOBALS['PROMPT_INJECTIONS']) || !is_array($GLOBALS['PROMPT_INJECTIONS'])) {
        $GLOBALS['PROMPT_INJECTIONS'] = [];
    }
    if (!isset($GLOBALS['PROMPT_INJECTIONS'][$slot]) || !is_array($GLOBALS['PROMPT_INJECTIONS'][$slot])) {
        $GLOBALS['PROMPT_INJECTIONS'][$slot] = [];
    }

    $GLOBALS['PROMPT_INJECTIONS'][$slot][$id] = [
        'id' => $id,
        'content' => $content,
        'priority' => $priority,
    ];

    return true;
}

function chimPromptInjectionContentToText($content, string $slot, array $context = []): string
{
    if (is_callable($content)) {
        try {
            $content = call_user_func($content, $slot, $context);
        } catch (Throwable $e) {
            error_log("[PromptInjections] Injection callback failed for {$slot}: " . $e->getMessage());
            return '';
        }
    }

    if (is_array($content)) {
        $parts = [];
        foreach ($content as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '') {
                $parts[] = $entry;
            }
        }
        $content = implode("\n", $parts);
    }

    return trim((string) $content);
}

function chimRenderPromptInjections(string $slot, array $context = []): string
{
    $slot = chimNormalizePromptInjectionKey($slot);
    $injections = $GLOBALS['PROMPT_INJECTIONS'][$slot] ?? [];
    if (!is_array($injections) || empty($injections)) {
        return '';
    }

    $entries = array_values($injections);
    usort($entries, static function ($a, $b) {
        $priorityCompare = intval($a['priority'] ?? 100) <=> intval($b['priority'] ?? 100);
        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $parts = [];
    foreach ($entries as $entry) {
        $content = chimPromptInjectionContentToText($entry['content'] ?? '', $slot, $context);
        if ($content !== '') {
            $parts[] = $content;
        }
    }

    return empty($parts) ? '' : "\n" . implode("\n", array_values(array_unique($parts)));
}

function chimRegisterActorProfileEnricher(string $id, callable $callback, int $priority = 100): bool
{
    $id = chimNormalizePromptInjectionKey($id);
    if ($id === '') {
        return false;
    }

    if (!isset($GLOBALS['PROMPT_ACTOR_PROFILE_ENRICHERS']) || !is_array($GLOBALS['PROMPT_ACTOR_PROFILE_ENRICHERS'])) {
        $GLOBALS['PROMPT_ACTOR_PROFILE_ENRICHERS'] = [];
    }

    $GLOBALS['PROMPT_ACTOR_PROFILE_ENRICHERS'][$id] = [
        'id' => $id,
        'callback' => $callback,
        'priority' => $priority,
    ];

    return true;
}

function chimBuildActorProfileEnrichmentText(string $actorName, string $actorType, array $context = []): string
{
    $enrichers = $GLOBALS['PROMPT_ACTOR_PROFILE_ENRICHERS'] ?? [];
    if (!is_array($enrichers) || empty($enrichers)) {
        return '';
    }

    $entries = [];
    foreach ($enrichers as $key => $enricher) {
        if (is_array($enricher) && isset($enricher['callback'])) {
            $entries[] = [
                'id' => (string) ($enricher['id'] ?? $key),
                'callback' => $enricher['callback'],
                'priority' => intval($enricher['priority'] ?? 100),
            ];
        } elseif (is_callable($enricher)) {
            $entries[] = [
                'id' => is_string($key) ? $key : 'legacy_' . count($entries),
                'callback' => $enricher,
                'priority' => 100,
            ];
        }
    }

    usort($entries, static function ($a, $b) {
        $priorityCompare = intval($a['priority'] ?? 100) <=> intval($b['priority'] ?? 100);
        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $parts = [];
    foreach ($entries as $entry) {
        if (!is_callable($entry['callback'])) {
            continue;
        }

        try {
            $result = call_user_func($entry['callback'], $actorName, $actorType, $context);
        } catch (Throwable $e) {
            error_log("[PromptInjections] Actor profile enricher failed for {$actorName}: " . $e->getMessage());
            continue;
        }

        foreach (is_array($result) ? $result : [$result] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $parts[] = rtrim($line, ". \t\r\n");
            }
        }
    }

    $parts = array_values(array_unique($parts));
    return empty($parts) ? '' : implode('. ', $parts);
}
