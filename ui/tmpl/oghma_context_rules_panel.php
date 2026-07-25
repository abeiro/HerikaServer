<?php

function oghma_render_context_rule_form(array $rule, bool $isNew = false): void
{
    $conditions = $rule['conditions'] ?? [];
    if (is_string($conditions)) {
        $conditions = json_decode($conditions, true);
    }
    if (!is_array($conditions)) {
        $conditions = [];
    }

    $ruleId = (int)($rule['id'] ?? 0);
    $enabledValue = strtolower(trim((string)($rule['enabled'] ?? '')));
    $enabled = $isNew || in_array($enabledValue, ['1', 't', 'true', 'on', 'yes'], true);
    $fields = [
        'npc' => ['NPC Name', 'Current speaking NPC name.'],
        'nearby_actor' => ['Nearby Actor', 'Any actor listed as present.'],
        'race' => ['Race', 'Current NPC race, including common aliases.'],
        'faction' => ['Faction', 'Current NPC faction name, editor ID, or form ID.'],
        'profile' => ['Profile ID', 'Current NPC profile ID.'],
        'location' => ['Location or Region', 'Current location, parent location, or region.'],
        'hold' => ['Hold', 'Current canonical or reported hold.'],
        'environment' => ['Environment', 'Use interior or exterior.'],
        'weather' => ['Weather', 'Current weather description.'],
        'event_type' => ['Event Type', 'Current request type, such as inputtext or combat.'],
    ];
    ?>
    <form method="post" class="context-rule-card">
        <input type="hidden" name="save_context_rule" value="1">
        <input type="hidden" name="context_rule_id" value="<?php echo $ruleId; ?>">
        <h3><?php echo $isNew ? 'Create Context Rule' : htmlspecialchars((string)($rule['label'] ?? 'Context Rule'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <div class="context-rule-grid">
            <div class="context-rule-field">
                <label>Rule Name</label>
                <small>Human-readable label used in audit logs.</small>
                <input type="text" name="context_rule_label" required value="<?php echo htmlspecialchars((string)($rule['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="context-rule-field">
                <label>Selector Type</label>
                <small>Choose how this rule finds Oghma articles.</small>
                <select name="context_rule_selector_type">
                    <?php foreach (['topic' => 'Exact Topic', 'tag' => 'Tag', 'category' => 'Category'] as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (($rule['selector_type'] ?? 'topic') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="context-rule-field">
                <label>Selector Value</label>
                <small>Exact topic alias, tag, or category to inject.</small>
                <input type="text" name="context_rule_selector_value" required value="<?php echo htmlspecialchars((string)($rule['selector_value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="context-rule-field">
                <label>Priority</label>
                <small>Lower numbers run first.</small>
                <input type="number" name="context_rule_priority" value="<?php echo (int)($rule['priority'] ?? 100); ?>">
            </div>
            <div class="context-rule-field">
                <label>Maximum Articles</label>
                <small>Limits tag and category selectors to 1-5 articles.</small>
                <input type="number" name="context_rule_max_articles" min="1" max="5" value="<?php echo max(1, min(5, (int)($rule['max_articles'] ?? 1))); ?>">
            </div>
            <?php foreach ($fields as $field => [$label, $description]): ?>
                <div class="context-rule-field">
                    <label><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                    <small><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?> Separate alternatives with commas.</small>
                    <input type="text" name="condition_<?php echo $field; ?>" value="<?php echo htmlspecialchars(oghma_context_rule_condition_text($conditions, $field), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="context-rule-actions">
            <label class="context-rule-enabled">
                <input type="checkbox" name="context_rule_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                Enabled
            </label>
            <button type="submit" class="btn-save"><?php echo $isNew ? 'Create Rule' : 'Save Rule'; ?></button>
            <?php if (!$isNew): ?>
                <button
                    type="submit"
                    name="delete_context_rule"
                    value="1"
                    class="btn-danger"
                    onclick="return confirm('Delete this context rule?');"
                >Delete</button>
            <?php endif; ?>
        </div>
    </form>
    <?php
}

?>
<style>
    .context-rule-intro {
        margin-bottom: 16px;
    }

    .context-rule-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .context-rule-card {
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        background: #242424;
        padding: 14px;
        margin-bottom: 14px;
    }

    .context-rule-card h3 {
        margin: 0 0 12px;
        color: rgb(242, 124, 17);
    }

    .context-rule-field label {
        display: block;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .context-rule-field small {
        display: block;
        min-height: 34px;
        margin-bottom: 5px;
        color: #b8b8b8;
    }

    .context-rule-field input,
    .context-rule-field select {
        width: 100%;
        box-sizing: border-box;
    }

    .context-rule-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
    }

    .context-rule-enabled {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-right: auto;
    }

    @media (max-width: 1100px) {
        .context-rule-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .context-rule-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div id="rules-tab" class="tab-content">
    <div class="full-width-section">
        <div class="context-rule-intro">
            <h2>&#x1F3AF; Oghma Context Rules</h2>
            <p>All populated conditions on a rule must match. Comma-separated values within one condition are alternatives. Leave every condition blank to create an always-on rule.</p>
            <p>Normal Oghma search and the existing Force Racial Oghma and Force Location Oghma settings continue to work alongside these rules.</p>
        </div>

        <?php
        oghma_render_context_rule_form([
            'priority' => 100,
            'selector_type' => 'topic',
            'max_articles' => 1,
            'conditions' => [],
        ], true);

        $contextRulesResult = pg_query(
            $conn,
            "SELECT id, label, enabled, priority, selector_type, selector_value, conditions, max_articles
               FROM {$schema}.oghma_context_rule
              ORDER BY priority, id"
        );
        if ($contextRulesResult) {
            $contextRuleCount = 0;
            while ($contextRule = pg_fetch_assoc($contextRulesResult)) {
                oghma_render_context_rule_form($contextRule, false);
                $contextRuleCount++;
            }
            if ($contextRuleCount === 0) {
                echo '<p>No context rules have been created.</p>';
            }
        } else {
            echo '<p>Unable to load context rules. Apply database updates, then refresh this page.</p>';
        }
        ?>
    </div>
</div>
