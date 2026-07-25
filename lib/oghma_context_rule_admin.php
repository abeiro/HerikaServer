<?php

function oghma_context_rule_values($value): array
{
    $values = preg_split('/\s*,\s*/u', trim((string)$value)) ?: [];

    return array_values(array_filter(array_map('trim', $values), static function ($entry) {
        return $entry !== '';
    }));
}

function oghma_context_rule_conditions_from_post(array $post): array
{
    $conditions = [];
    $fields = [
        'npc',
        'nearby_actor',
        'race',
        'faction',
        'profile',
        'location',
        'hold',
        'environment',
        'weather',
        'event_type',
    ];

    foreach ($fields as $field) {
        $values = oghma_context_rule_values($post['condition_' . $field] ?? '');
        if (!empty($values)) {
            $conditions[$field] = $values;
        }
    }

    return $conditions;
}

function oghma_context_rule_condition_text(array $conditions, string $field): string
{
    $values = $conditions[$field] ?? [];
    if (!is_array($values)) {
        return trim((string)$values);
    }

    return implode(', ', array_map('strval', $values));
}

function oghma_context_rule_handle_post($conn, string $schema, array $post, string $method): string
{
    if ($method !== 'POST') {
        return '';
    }

    if (isset($post['delete_context_rule'])) {
        $ruleId = max(0, (int)($post['context_rule_id'] ?? 0));
        if ($ruleId <= 0) {
            return '';
        }

        $result = pg_query_params(
            $conn,
            "DELETE FROM {$schema}.oghma_context_rule WHERE id = $1",
            [$ruleId]
        );

        return $result ? '<p>Context rule deleted.</p>' : '<p>Unable to delete context rule.</p>';
    }

    if (!isset($post['save_context_rule'])) {
        return '';
    }

    $ruleId = max(0, (int)($post['context_rule_id'] ?? 0));
    $label = trim((string)($post['context_rule_label'] ?? ''));
    $enabled = isset($post['context_rule_enabled']) && $post['context_rule_enabled'] === '1';
    $priority = (int)($post['context_rule_priority'] ?? 100);
    $selectorType = strtolower(trim((string)($post['context_rule_selector_type'] ?? 'topic')));
    $selectorValue = trim((string)($post['context_rule_selector_value'] ?? ''));
    $maxArticles = max(1, min(5, (int)($post['context_rule_max_articles'] ?? 1)));
    $conditionsJson = json_encode(
        oghma_context_rule_conditions_from_post($post),
        JSON_UNESCAPED_UNICODE
    );

    if ($label === '' || $selectorValue === '' || !in_array($selectorType, ['topic', 'tag', 'category'], true)) {
        return '<p>Context rule name, selector type, and selector value are required.</p>';
    }

    if ($ruleId > 0) {
        $result = pg_query_params(
            $conn,
            "UPDATE {$schema}.oghma_context_rule
                SET label = $1, enabled = $2, priority = $3, selector_type = $4,
                    selector_value = $5, conditions = $6::jsonb, max_articles = $7, updated_at = NOW()
              WHERE id = $8",
            [$label, $enabled ? 't' : 'f', $priority, $selectorType, $selectorValue, $conditionsJson, $maxArticles, $ruleId]
        );

        return $result ? '<p>Context rule updated.</p>' : '<p>Unable to update context rule.</p>';
    }

    $result = pg_query_params(
        $conn,
        "INSERT INTO {$schema}.oghma_context_rule
            (label, enabled, priority, selector_type, selector_value, conditions, max_articles)
         VALUES ($1, $2, $3, $4, $5, $6::jsonb, $7)",
        [$label, $enabled ? 't' : 'f', $priority, $selectorType, $selectorValue, $conditionsJson, $maxArticles]
    );

    return $result ? '<p>Context rule created.</p>' : '<p>Unable to create context rule.</p>';
}
