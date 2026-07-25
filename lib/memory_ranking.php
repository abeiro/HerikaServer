<?php

function chimMemoryCandidateNumber(array $candidate, string $field, float $default): float
{
    $value = $candidate[$field] ?? null;

    return is_numeric($value) ? (float)$value : $default;
}

function chimMemoryCandidateMixedDistance(array $candidate): float
{
    if (isset($candidate['mixed_distance']) && is_numeric($candidate['mixed_distance'])) {
        return (float)$candidate['mixed_distance'];
    }

    $distance = chimMemoryCandidateNumber($candidate, 'distance', INF);
    $keywordRank = chimMemoryCandidateNumber($candidate, 'rank_fts', 0.0);

    return $distance - $keywordRank;
}

function chimSelectBestHybridMemoryCandidate(array $candidates): ?array
{
    $best = null;
    $bestMixedDistance = INF;
    $bestDistance = INF;
    $bestGamets = -INF;
    $bestRowId = -INF;

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $mixedDistance = chimMemoryCandidateMixedDistance($candidate);
        $distance = chimMemoryCandidateNumber($candidate, 'distance', INF);
        $gamets = chimMemoryCandidateNumber($candidate, 'gamets_truncated', -INF);
        $rowId = chimMemoryCandidateNumber($candidate, 'rowid', -INF);

        $isBetter = $mixedDistance < $bestMixedDistance
            || ($mixedDistance === $bestMixedDistance && $distance < $bestDistance)
            || ($mixedDistance === $bestMixedDistance && $distance === $bestDistance && $gamets > $bestGamets)
            || (
                $mixedDistance === $bestMixedDistance
                && $distance === $bestDistance
                && $gamets === $bestGamets
                && $rowId > $bestRowId
            );

        if (!$isBetter) {
            continue;
        }

        $best = $candidate;
        $bestMixedDistance = $mixedDistance;
        $bestDistance = $distance;
        $bestGamets = $gamets;
        $bestRowId = $rowId;
    }

    if ($best === null) {
        return null;
    }

    $best['mixed_distance'] = $bestMixedDistance;
    $best['distance'] = $bestDistance;
    $best['rank_any'] = 1.4 - $bestMixedDistance;
    $best['rank_all'] = 1.4 - $bestDistance;

    return $best;
}

function chimBuildMemoryRecallAuditCandidates(array $candidates, ?array $selected, int $limit = 10): array
{
    $limit = max(1, min(25, $limit));
    $selectedRowId = strval($selected['rowid'] ?? '');
    $normalized = [];

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $distance = chimMemoryCandidateNumber($candidate, 'distance', INF);
        $keywordScore = chimMemoryCandidateNumber($candidate, 'rank_fts', 0.0);
        $gameTime = chimMemoryCandidateNumber($candidate, 'gamets_truncated', -INF);
        $mixedDistance = chimMemoryCandidateMixedDistance($candidate);
        if (!is_finite($distance) || !is_finite($mixedDistance)) {
            continue;
        }

        $summary = trim(strval($candidate['summary'] ?? ''));
        if (function_exists('mb_substr')) {
            $summary = mb_substr($summary, 0, 300, 'UTF-8');
        } else {
            $summary = substr($summary, 0, 300);
        }

        $rowId = strval($candidate['rowid'] ?? '');
        $normalized[] = [
            'rowid' => $rowId,
            'semantic_distance' => $distance,
            'keyword_score' => $keywordScore,
            'hybrid_score' => 1.4 - $mixedDistance,
            'hybrid_distance' => $mixedDistance,
            'game_time' => is_finite($gameTime) ? $gameTime : null,
            'selected' => $selectedRowId !== '' && $rowId === $selectedRowId,
            'memory_preview' => $summary,
        ];
    }

    usort($normalized, static function (array $left, array $right): int {
        $hybridCompare = ($left['hybrid_distance'] ?? INF) <=> ($right['hybrid_distance'] ?? INF);
        if ($hybridCompare !== 0) {
            return $hybridCompare;
        }

        $distanceCompare = ($left['semantic_distance'] ?? INF) <=> ($right['semantic_distance'] ?? INF);
        if ($distanceCompare !== 0) {
            return $distanceCompare;
        }

        $gameTimeCompare = ($right['game_time'] ?? -INF) <=> ($left['game_time'] ?? -INF);
        if ($gameTimeCompare !== 0) {
            return $gameTimeCompare;
        }

        return intval($right['rowid'] ?? 0) <=> intval($left['rowid'] ?? 0);
    });

    return array_slice($normalized, 0, $limit);
}
