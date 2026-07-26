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
