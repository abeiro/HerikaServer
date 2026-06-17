<?php

function getDefaultEmoteMoods() {
    return [
        'sassy',
        'assertive',
        'sexy',
        'smug',
        'kindly',
        'lovely',
        'seductive',
        'sarcastic',
        'smirking',
        'amused',
        'irritated',
        'playful',
        'neutral',
        'teasing',
        'desperate',
        'scared',
        'pleading',
        'sad',
        'happy',
        'angry',
        'drunk',
        'shy',
        'surprised',
    ];
}

function expandEmoteMoodToken($token) {
    $token = strtolower(trim((string)$token));
    if ($token === '') {
        return [];
    }

    $defaultMoods = getDefaultEmoteMoods();
    $defaultLookup = array_fill_keys($defaultMoods, true);
    $deprecatedMoodAliases = [
        'sardonic' => ['sarcastic'],
        'mocking' => ['sarcastic'],
        'default' => ['neutral'],
        'distressed' => ['scared'],
        'assisting' => [],
    ];
    if (isset($defaultLookup[$token])) {
        return [$token];
    }
    if (isset($deprecatedMoodAliases[$token])) {
        return $deprecatedMoodAliases[$token];
    }

    $compactToken = preg_replace('/[^a-z]/', '', $token);
    if ($compactToken === '') {
        return [];
    }
    if (isset($defaultLookup[$compactToken])) {
        return [$compactToken];
    }
    if (isset($deprecatedMoodAliases[$compactToken])) {
        return $deprecatedMoodAliases[$compactToken];
    }

    $sortedMoods = array_values(array_unique(array_merge($defaultMoods, array_keys($deprecatedMoodAliases))));
    usort($sortedMoods, function ($left, $right) {
        return strlen($right) <=> strlen($left);
    });

    $pattern = '/'.implode('|', array_map(function ($mood) {
        return preg_quote($mood, '/');
    }, $sortedMoods)).'/';

    preg_match_all($pattern, $compactToken, $matches);
    $expandedMoods = $matches[0] ?? [];
    if (!empty($expandedMoods) && implode('', $expandedMoods) === $compactToken) {
        $normalizedExpandedMoods = [];
        foreach ($expandedMoods as $expandedMood) {
            foreach (expandEmoteMoodToken($expandedMood) as $normalizedMood) {
                $normalizedExpandedMoods[] = $normalizedMood;
            }
        }
        return $normalizedExpandedMoods;
    }

    return [$token];
}

function normalizeEmoteMoods($rawMoods) {
    if (is_array($rawMoods)) {
        $tokens = $rawMoods;
    } else {
        $tokens = preg_split('/[\r\n,|]+/', (string)$rawMoods);
    }

    $normalizedMoods = [];
    $seenMoods = [];
    foreach ($tokens as $token) {
        foreach (expandEmoteMoodToken($token) as $mood) {
            if ($mood === '' || isset($seenMoods[$mood])) {
                continue;
            }

            $normalizedMoods[] = $mood;
            $seenMoods[$mood] = true;
        }
    }

    return $normalizedMoods;
}

function extractFirstEmoteMood($rawMoods, $fallback = '') {
    $normalizedMoods = normalizeEmoteMoods($rawMoods);
    if (!empty($normalizedMoods)) {
        return $normalizedMoods[0];
    }

    return trim((string)$fallback);
}
