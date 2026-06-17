<?php

function aiProfileMergeRequestData(): array
{
    return array_merge($_GET ?? [], $_POST ?? []);
}

function aiProfileEventLabel(string $wrapperType, string $role = ''): string
{
    if ($wrapperType === 'last_dialogue') {
        return 'Dialogue Line';
    }

    $labels = [
        'diary_entry' => 'Diary Entry',
        'sent_letter' => 'Sent Letter',
        'event' => 'Background Event',
        'last_known_location' => 'Last Known Location',
    ];

    return $labels[$wrapperType] ?? ucwords(str_replace('_', ' ', $wrapperType));
}

function aiProfileBuildEventId(string $prefix, int $index, string $content, $gamets = null): string
{
    return $prefix . '_' . $index . '_' . substr(md5((string)$gamets . '|' . $content), 0, 12);
}

function aiProfileIsNpcSpokenContent(string $content, string $npcName): bool
{
    $content = trim($content);
    if ($content === '' || $npcName === '') {
        return false;
    }

    return (bool)preg_match('/^' . preg_quote($npcName, '/') . '\s*:/i', $content);
}

function aiProfileFilterHistoricContext(array $contextDataHistoric, string $npcName): array
{
    return filterHistoricContextForNarratorVisibility($contextDataHistoric, $npcName);
}

function aiProfileBuildPreviewEvents(string $npcName, array $currentNpcData, $db, int $eventLimit = 100): array
{
    $eventLimit = max(10, min(200, $eventLimit));

    $npcNameEsc = $db->escape($npcName);
    $query = "SELECT max(gamets) as gamets from speech where
        (speaker='$npcNameEsc' or listener='$npcNameEsc' or companions like '%|$npcNameEsc|%')";
    $lastIt = $db->fetchOne($query);
    $lastItNumber = intval($lastIt["gamets"] ?? 0);

    $contextPoolSize = max(200, $eventLimit);
    $sqlfilter = " and gamets<$lastItNumber and type<>'prechat' and type<>'itemfound' and type<>'infoaction' and type<>'npcspellcast' and data not like '%inner thoughts%'";
    $contextDataHistoric = DataLastDataExpandedFor($npcName, $contextPoolSize * -1, $sqlfilter);
    $contextDataHistoric = aiProfileFilterHistoricContext($contextDataHistoric, $npcName);

    $events = [];
    $sortKey = 0;

    foreach ($contextDataHistoric as $index => $element) {
        $role = (string)($element["role"] ?? "user");
        $rawContent = trim((string)($element["content"] ?? ""));
        $isNpcSpokenLine = ($role === "assistant") || aiProfileIsNpcSpokenContent($rawContent, $npcName);

        if (!$isNpcSpokenLine) {
            continue;
        }

        if (aiProfileIsNpcSpokenContent($rawContent, $npcName)) {
            $content = $rawContent;
        } else {
            $content = trim($npcName . ": " . $rawContent);
        }

        if ($content === '') {
            continue;
        }

        $events[] = [
            "id" => aiProfileBuildEventId("ctx", $index, $content, $element["gamets"] ?? null),
            "wrapper_type" => "last_dialogue",
            "role" => $role,
            "label" => aiProfileEventLabel("last_dialogue", $role),
            "content" => $content,
            "raw_content" => $content,
            "gamets" => isset($element["gamets"]) ? intval($element["gamets"]) : null,
            "sort_key" => $sortKey++,
        ];
    }

    $diaryQuery = "SELECT content,gamets,topic FROM diarylog
        where people='$npcNameEsc' and gamets>$lastItNumber and (topic='Sent Letter' or topic='Journal Note')
        order by gamets desc ,ts desc limit 5 offset 0";
    $diaryEntries = $db->fetchAll($diaryQuery);

    $structuredEvents = [];
    foreach (array_reverse($diaryEntries) as $index => $dentry) {
        $wrapperType = ($dentry["topic"] === "Sent Letter") ? "sent_letter" : "diary_entry";
        $content = trim((string)($dentry["content"] ?? ""));
        if ($content === '') {
            continue;
        }

        $structuredEvents[] = [
            "id" => aiProfileBuildEventId("diary", $index, $content, $dentry["gamets"] ?? null),
            "wrapper_type" => $wrapperType,
            "role" => "system",
            "label" => aiProfileEventLabel($wrapperType),
            "content" => $content,
            "raw_content" => $content,
            "gamets" => isset($dentry["gamets"]) ? intval($dentry["gamets"]) : null,
        ];
    }

    if ($lastItNumber > 0) {
        $backgroundQuery = "SELECT gamets,data FROM eventlog where type='backgroundaction' and gamets>$lastItNumber order by gamets asc ,ts asc";
        $backgroundEvents = $db->fetchAll($backgroundQuery);
        foreach ($backgroundEvents as $index => $event) {
            $eventParsed = json_decode($event["data"], true);
            if (!is_array($eventParsed)) {
                continue;
            }
            if (($eventParsed["source"] ?? "") !== "AIAgent.esp") {
                continue;
            }
            if (empty($eventParsed["description"]) || $eventParsed["description"] === "unknown") {
                continue;
            }
            if (($eventParsed["actor"] ?? "") !== $npcName) {
                continue;
            }

            $content = trim((string)$eventParsed["description"]);
            if ($content === '') {
                continue;
            }

            $structuredEvents[] = [
                "id" => aiProfileBuildEventId("bg", $index, $content, $event["gamets"] ?? null),
                "wrapper_type" => "event",
                "role" => "system",
                "label" => aiProfileEventLabel("event"),
                "content" => $content,
                "raw_content" => $content,
                "gamets" => isset($event["gamets"]) ? intval($event["gamets"]) : null,
            ];
        }
    }

    usort($structuredEvents, function ($a, $b) {
        $aGamets = isset($a["gamets"]) ? intval($a["gamets"]) : 0;
        $bGamets = isset($b["gamets"]) ? intval($b["gamets"]) : 0;
        return $aGamets <=> $bGamets;
    });

    foreach ($structuredEvents as $event) {
        $event["sort_key"] = $sortKey++;
        $events[] = $event;
    }

    $totalAvailable = count($events);
    if ($totalAvailable > $eventLimit) {
        $events = array_slice($events, $totalAvailable - $eventLimit);
    }

    return [
        "events" => array_values($events),
        "total_available" => $totalAvailable,
        "used_count" => count($events),
        "last_interaction_gamets" => $lastItNumber,
    ];
}

function aiProfileNormalizeSelectedEvents($selectedEvents): array
{
    if (!is_array($selectedEvents)) {
        return [];
    }

    $normalized = [];
    foreach ($selectedEvents as $event) {
        if (!is_array($event)) {
            continue;
        }

        $wrapperType = trim((string)($event["wrapper_type"] ?? ""));
        $content = trim((string)($event["content"] ?? ""));
        $rawContent = trim((string)($event["raw_content"] ?? $content));
        if ($wrapperType === '' || $content === '') {
            continue;
        }

        $normalized[] = [
            "id" => (string)($event["id"] ?? ''),
            "wrapper_type" => $wrapperType,
            "role" => (string)($event["role"] ?? 'system'),
            "label" => (string)($event["label"] ?? aiProfileEventLabel($wrapperType, (string)($event["role"] ?? 'system'))),
            "content" => $content,
            "raw_content" => $rawContent,
            "gamets" => isset($event["gamets"]) && $event["gamets"] !== null && $event["gamets"] !== ''
                ? intval($event["gamets"])
                : null,
            "sort_key" => isset($event["sort_key"]) ? intval($event["sort_key"]) : count($normalized),
        ];
    }

    usort($normalized, function ($a, $b) {
        return intval($a["sort_key"] ?? 0) <=> intval($b["sort_key"] ?? 0);
    });

    return array_values($normalized);
}

function aiProfileBuildHistoryFromSelectedEvents(array $selectedEvents, string $npcName): string
{
    if (empty($selectedEvents)) {
        return "No recent dialogue or event history was selected for {$npcName}. Use the character sheet, saved memory, and any custom instructions to infer the profile.";
    }

    $history = "\n<last_dialogue>\n";

    foreach ($selectedEvents as $event) {
        if (($event["wrapper_type"] ?? '') !== 'last_dialogue') {
            continue;
        }
        $history .= trim((string)$event["content"]) . PHP_EOL . PHP_EOL;
    }

    $history .= "\n</last_dialogue>\n";

    $previousEventGamets = null;
    foreach ($selectedEvents as $event) {
        $wrapperType = (string)($event["wrapper_type"] ?? '');
        if ($wrapperType === '' || $wrapperType === 'last_dialogue') {
            continue;
        }

        $content = trim((string)($event["raw_content"] ?? $event["content"] ?? ''));
        if ($content === '') {
            continue;
        }

        if ($wrapperType === 'event') {
            $currentGamets = isset($event["gamets"]) && $event["gamets"] !== null ? intval($event["gamets"]) : null;
            if ($previousEventGamets !== null && $currentGamets !== null) {
                $hours = ($currentGamets - $previousEventGamets) * 0.0000024;
                $content = "* {$hours} hours later: {$content}";
            }
            if ($currentGamets !== null) {
                $previousEventGamets = $currentGamets;
            }
        }

        $history .= "\n<{$wrapperType}>\n{$content}\n</{$wrapperType}>\n";
    }

    return $history;
}
