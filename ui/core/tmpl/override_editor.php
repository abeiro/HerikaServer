<?php
// Unified Override Editor Component
// Configurable for both NPC Master (extended_data) and Core Profiles (metadata)

// Configuration parameters (must be set before including this file)
// $overrideEditorConfig = [
//     'mode' => 'npc' | 'profile',  // Which mode to operate in
//     'fieldName' => 'extended_data' | 'metadata',  // Which field to save to
//     'settingsCatalog' => [...],  // Setting schema keyed by runtime override key
//     'reservedKeys' => [...],  // Keys that should not appear in UI (only for npc mode)
//     'currentData' => [],  // Current override data
//     'systemFields' => [],  // System-managed fields (only for npc mode)
// ];

if (!isset($overrideEditorConfig)) {
    throw new Exception('Override editor config not set');
}

$mode = $overrideEditorConfig['mode'] ?? 'npc';
$fieldName = $overrideEditorConfig['fieldName'] ?? 'extended_data';
$settingsCatalog = $overrideEditorConfig['settingsCatalog'] ?? [];
$reservedKeys = $overrideEditorConfig['reservedKeys'] ?? [];
$currentData = $overrideEditorConfig['currentData'] ?? [];
$systemFields = $overrideEditorConfig['systemFields'] ?? [];
unset($currentData['MINIME_T5']);
unset($currentData['ENFORCE_ACTIONS_PROMPT']);

$isNpcMode = ($mode === 'npc');
$isProfileMode = ($mode === 'profile');

// CSS prefix for unique styling
$prefix = $isProfileMode ? 'prof-' : '';

// Icon mapping function
function ovr_icon_for(string $key): string {
    $u = strtoupper($key);
    if (strpos($u, 'TTS') !== false) return '🔊';
    if (strpos($u, 'DIARY') !== false) return '📙';
    if (strpos($u, 'RECHAT') === 0) return '🔁';
    if (strpos($u, 'CONTEXT_HISTORY') === 0) return '🧠';
    if (strpos($u, 'OGHMA') === 0) return '🧾';
    if (strpos($u, 'QUEST_') === 0) return '🧭';
    if (strpos($u, 'LANG_') === 0 || strpos($u, 'CORE_LANG') === 0) return '🌐';
    if ($u === 'HERIKA_ANIMATIONS') return '🎞️';
    if (strpos($u, 'DYNAMIC') !== false) return '🔄';
    if (strpos($u, 'BORED') !== false) return '💤';
    return '⚙️';
}

$schema = is_array($settingsCatalog) ? $settingsCatalog : [];
?>

<style>
.<?= $prefix ?>ovr-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    <?php if ($isNpcMode): ?>
    border: 1px solid #4a4a4a;
    border-radius: 8px;
    padding: 12px;
    background: #262626;
    margin-bottom: 12px;
    <?php endif; ?>
}

<?php if ($isNpcMode): ?>
.<?= $prefix ?>ovr-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.<?= $prefix ?>ovr-title {
    font-weight: 700;
    color: rgb(242, 124, 17);
    font-size: 16px;
}
<?php endif; ?>

.<?= $prefix ?>ovr-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    <?php if ($isNpcMode): ?>margin-bottom: 12px;<?php endif; ?>
}

<?php if ($isProfileMode): ?>
.prof-ovr-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.prof-ovr-category {
    min-width: 0;
    padding: 10px;
    border: 1px solid #4a4a4a;
    border-radius: 8px;
    background: #202020;
}

.prof-ovr-category-title {
    margin: 0 0 8px;
    padding-bottom: 7px;
    border-bottom: 1px solid rgba(242, 124, 17, 0.35);
    color: rgb(242, 124, 17);
    font-size: 14px;
    font-weight: 700;
}

.prof-ovr-category-settings {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.prof-ovr-inline-item {
    display: grid;
    grid-template-columns: minmax(190px, 1fr) minmax(180px, 0.9fr);
    gap: 10px;
    align-items: center;
    padding: 9px;
    border: 1px solid #3e3e3e;
    border-radius: 6px;
    background: #1a1a1a;
}

.prof-ovr-inline-info {
    min-width: 0;
}

.prof-ovr-inline-name {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #e9efff;
    font-size: 13px;
    font-weight: 700;
}

.prof-ovr-inline-description,
.prof-ovr-inherited-value {
    margin-top: 3px;
    color: #9fb1c9;
    font-size: 11px;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.prof-ovr-inline-controls {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 8px;
    align-items: center;
}

.prof-ovr-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #e9efff;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.prof-ovr-toggle input {
    accent-color: #2f8f4e;
}

.prof-ovr-inline-input {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 7px 8px;
    border: 1px solid #4a4a4a;
    border-radius: 5px;
    background: #171717;
    color: #e9efff;
    font-size: 12px;
}

.prof-ovr-inline-input:focus {
    outline: none;
    border-color: rgb(242, 124, 17);
}

.prof-ovr-inline-input:disabled {
    cursor: not-allowed;
    opacity: 0.48;
}

.prof-ovr-inline-item:not(.enabled) .prof-ovr-inline-info {
    opacity: 0.7;
}

@media (max-width: 1180px) {
    .prof-ovr-list {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .prof-ovr-inline-item {
        grid-template-columns: 1fr;
    }
}
<?php endif; ?>

.<?= $prefix ?>ovr-item {
    background: <?= $isProfileMode ? '#1a1a1a' : '#2a2a2a' ?>;
    border: 1px solid #4a4a4a;
    border-radius: <?= $isProfileMode ? '8px' : '6px' ?>;
    padding: 10px;
    display: grid;
    grid-template-columns: <?= $isProfileMode ? '32px' : 'auto' ?> 1fr auto;
    gap: <?= $isProfileMode ? '10px' : '12px' ?>;
    align-items: center;
}

.<?= $prefix ?>ovr-icon {
    font-size: 20px;
    text-align: center;
}

.<?= $prefix ?>ovr-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.<?= $prefix ?>ovr-key {
    font-weight: <?= $isProfileMode ? '700' : '600' ?>;
    color: #e9efff;
    font-size: 14px;
}

.<?= $prefix ?>ovr-value {
    color: #9fb1c9;
    font-size: <?= $isProfileMode ? '12px' : '13px' ?>;
    <?php if ($isNpcMode): ?>font-family: monospace;<?php endif; ?>
    overflow-wrap: anywhere;
}

.<?= $prefix ?>ovr-actions {
    display: flex;
    gap: 6px;
}

.<?= $prefix ?>ovr-empty {
    color: #9fb1c9;
    font-size: <?= $isProfileMode ? '13px' : 'italic' ?>;
    text-align: center;
    padding: <?= $isProfileMode ? '16px' : '20px' ?>;
    <?php if ($isNpcMode): ?>font-style: italic;<?php endif; ?>
}

.btn-<?= $prefix ?>ovr {
    padding: 4px <?= $isProfileMode ? '10px' : '8px' ?>;
    border-radius: 4px;
    border: 1px solid #4a4a4a;
    background: #3a3a3a;
    color: #e9efff;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.15s ease;
}

.btn-<?= $prefix ?>ovr:hover {
    background: #4a4a4a;
}

.btn-<?= $prefix ?>ovr.danger {
    <?php if ($isProfileMode): ?>
    color: #ff6b6b;
    <?php else: ?>
    background: #5a2a2a;
    border-color: #7a3a3a;
    <?php endif; ?>
}

.btn-<?= $prefix ?>ovr.danger:hover {
    background: <?= $isProfileMode ? '#5a2a2a' : '#6a2a2a' ?>;
    <?php if ($isProfileMode): ?>border-color: #7a3a3a;<?php endif; ?>
}

.btn-add-<?= $prefix ?>ovr {
    padding: 8px <?= $isProfileMode ? '14px' : '12px' ?>;
    border-radius: 6px;
    border: 1px solid <?= $isProfileMode ? 'rgb(242, 124, 17)' : '#4a4a4a' ?>;
    background: rgb(242, 124, 17);
    color: #111;
    cursor: pointer;
    font-weight: 700;
    <?php if ($isProfileMode): ?>
    font-size: 14px;
    transition: all 0.2s ease;
    <?php else: ?>
    width: 100%;
    <?php endif; ?>
}

.btn-add-<?= $prefix ?>ovr:hover {
    <?php if ($isProfileMode): ?>
    background: rgb(255, 140, 30);
    transform: translateY(-1px);
    <?php else: ?>
    opacity: 0.9;
    <?php endif; ?>
}

.<?= $prefix ?>ovr-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,<?= $isProfileMode ? '0.7' : '0.65' ?>);
    z-index: <?= $isProfileMode ? '10010' : '10100' ?>;
    align-items: center;
    justify-content: center;
}

.<?= $prefix ?>ovr-modal {
    background: #2a2a2a;
    border: 1px solid #4a4a4a;
    border-radius: 10px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.<?= $prefix ?>ovr-modal-header {
    padding: <?= $isProfileMode ? '14px 16px' : '12px 14px' ?>;
    border-bottom: 1px solid #4a4a4a;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.<?= $prefix ?>ovr-modal-title {
    font-weight: 700;
    color: rgb(242, 124, 17);
    font-size: <?= $isProfileMode ? '16px' : '18px' ?>;
}

.<?= $prefix ?>ovr-modal-body {
    padding: <?= $isProfileMode ? '16px' : '14px' ?>;
    overflow-y: auto;
    flex: 1;
}

.<?= $prefix ?>ovr-modal-footer {
    padding: <?= $isProfileMode ? '12px 16px' : '12px 14px' ?>;
    border-top: 1px solid #4a4a4a;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.<?= $prefix ?>ovr-search {
    width: 100%;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #4a4a4a;
    background: #2a2a2a;
    color: #e9efff;
    margin-bottom: 12px;
}

.<?= $prefix ?>ovr-settings-list {
    display: flex;
    flex-direction: column;
    gap: <?= $isProfileMode ? '8px' : '6px' ?>;
    <?php if ($isNpcMode): ?>
    max-height: 500px;
    overflow-y: auto;
    <?php endif; ?>
}

.<?= $prefix ?>ovr-setting-option {
    padding: 10px;
    border: 1px solid #4a4a4a;
    border-radius: <?= $isProfileMode ? '8px' : '6px' ?>;
    cursor: pointer;
    background: <?= $isProfileMode ? '#1a1a1a' : '#2a2a2a' ?>;
    transition: all 0.15s ease;
}

.<?= $prefix ?>ovr-setting-option:hover {
    background: <?= $isProfileMode ? '#2a2a2a' : '#333333' ?>;
    <?php if ($isProfileMode): ?>border-color: rgb(242, 124, 17);<?php endif; ?>
}

.<?= $prefix ?>ovr-setting-name {
    font-weight: 600;
    color: #e9efff;
    margin-bottom: 4px;
    font-size: 14px;
}

.<?= $prefix ?>ovr-setting-desc {
    color: #9fb1c9;
    font-size: 12px;
}

.<?= $prefix ?>ovr-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    <?php if ($isNpcMode): ?>margin-top: 12px;<?php endif; ?>
}

.<?= $prefix ?>ovr-input-label {
    font-weight: <?= $isProfileMode ? '700' : '600' ?>;
    color: rgb(242, 124, 17);
    margin-bottom: 6px;
    display: block;
    font-size: 14px;
}

.<?= $prefix ?>ovr-input {
    width: 100%;
    padding: 8px <?= $isProfileMode ? '10px' : '8px' ?>;
    border-radius: 6px;
    border: 1px solid #4a4a4a;
    background: <?= $isProfileMode ? '#1a1a1a' : '#2a2a2a' ?>;
    color: #e9efff;
    font-size: 14px;
}

.<?= $prefix ?>ovr-input:focus {
    outline: none;
    border-color: rgb(242, 124, 17);
}

<?php if ($isNpcMode): ?>
.advanced-json-toggle {
    margin-top: 12px;
    cursor: pointer;
    color: #9fb1c9;
    font-size: 13px;
    user-select: none;
}

.advanced-json-toggle:hover {
    color: rgb(242, 124, 17);
}

.advanced-json-content {
    display: none;
    margin-top: 8px;
    border: 1px solid #4a4a4a;
    border-radius: 6px;
    padding: 8px;
    background: #1a1a1a;
}
<?php endif; ?>
</style>

<div class="<?= $prefix ?>ovr-container">
    <?php if ($isNpcMode): ?>
    <div class="<?= $prefix ?>ovr-header">
        <div class="<?= $prefix ?>ovr-title">Current Overrides</div>
    </div>
    <?php endif; ?>
    
    <div id="<?= $prefix ?>ovr-list" class="<?= $prefix ?>ovr-list"></div>

    <?php if ($isNpcMode): ?>
    <button type="button" id="btn-add-<?= $prefix ?>ovr" class="btn-add-<?= $prefix ?>ovr">
        + Add Override
    </button>

    <div class="advanced-json-toggle" id="advanced-json-toggle">
        ▼ Advanced: Raw JSON Editor (click to expand)
    </div>
    <div class="advanced-json-content" id="advanced-json-content">
        <div id="extended_data_raw_editor" style="min-height: 300px;"></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($isNpcMode): ?>
<!-- Modal -->
<div id="<?= $prefix ?>ovr-modal" class="<?= $prefix ?>ovr-modal-backdrop">
    <div class="<?= $prefix ?>ovr-modal">
        <div class="<?= $prefix ?>ovr-modal-header">
            <div class="<?= $prefix ?>ovr-modal-title" id="<?= $prefix ?>ovr-modal-title">Add Override</div>
            <button type="button" class="btn-<?= $prefix ?>ovr" id="<?= $prefix ?>ovr-modal-close">Close</button>
        </div>
        <div class="<?= $prefix ?>ovr-modal-body">
            <div id="<?= $prefix ?>ovr-select-stage">
                <?php if ($isNpcMode): ?>
                <input type="text" id="<?= $prefix ?>ovr-search" class="<?= $prefix ?>ovr-search" placeholder="Filter settings..." style="margin-bottom:12px;">
                <?php endif; ?>
                <div style="color:#9fb1c9; font-size:13px; margin-bottom:8px;">
                    Select a <?= $isProfileMode ? 'global ' : '' ?>setting to override:
                </div>
                <div id="<?= $prefix ?>ovr-settings-list" class="<?= $prefix ?>ovr-settings-list"></div>
            </div>
            <div id="<?= $prefix ?>ovr-value-stage" style="display: none;">
                <div id="<?= $prefix ?>ovr-value-form"></div>
            </div>
        </div>
        <div class="<?= $prefix ?>ovr-modal-footer">
            <button type="button" class="btn-<?= $prefix ?>ovr" id="<?= $prefix ?>ovr-modal-back" style="display:none;">Back</button>
            <button type="button" class="btn-<?= $prefix ?>ovr" id="<?= $prefix ?>ovr-modal-cancel">Cancel</button>
            <button type="button" class="btn-add-<?= $prefix ?>ovr" id="<?= $prefix ?>ovr-modal-save" style="width:auto; display:none;">Save Override</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    const MODE = <?= json_encode($mode) ?>;
    const IS_NPC_MODE = MODE === 'npc';
    // Display name mappings for UI labels
    const displayNames = { 'xtts-fastapi': 'xtts/chatterbox' };
    const IS_PROFILE_MODE = MODE === 'profile';
    const FIELD_NAME = <?= json_encode($fieldName) ?>;
    const PREFIX = <?= json_encode($prefix) ?>;
    const RESERVED_KEYS = <?= json_encode($reservedKeys) ?>;
    const SYSTEM_FIELDS = <?= json_encode($systemFields) ?>;
    const CURRENT_DATA = <?= json_encode($currentData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const SCHEMA = <?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    
    let overridesState = Object.fromEntries(Object.entries(CURRENT_DATA).filter(([key]) => !RESERVED_KEYS.includes(key)));
    let selectedSetting = null;
    let editingKey = null;
    let rawJsonEditor = null;
    
    // Get element by ID with prefix
    function el(id) {
        return document.getElementById(PREFIX + id);
    }
    
    // Icon mapping
    function getIcon(key) {
        const u = key.toUpperCase();
        if (u.includes('TTS')) return '🔊';
        if (u.includes('DIARY')) return '📙';
        if (u.startsWith('RECHAT')) return '🔁';
        if (u.startsWith('CONTEXT_HISTORY')) return '🧠';
        if (u.startsWith('OGHMA')) return '🧾';
        if (u.startsWith('QUEST_')) return '🧭';
        if (u.includes('LANG_') || u.includes('CORE_LANG')) return '🌐';
        if (u === 'HERIKA_ANIMATIONS') return '🎞️';
        if (u.includes('DYNAMIC')) return '🔄';
        if (u.includes('BORED')) return '💤';
        return '⚙️';
    }
    
    // Custom label mapping
    function getLabel(key) {
        if (SCHEMA[key] && SCHEMA[key].ui_label) {
            return SCHEMA[key].ui_label;
        }
        const customLabels = {
            'RECHAT_H': 'RECHAT RESPONSE ROUNDS',
            'RECHAT_P': 'RECHAT PROBAILITY',
            'OGHMA_AMOUNT': 'OGHMA ARTICLES AMOUNT',
            'BORED_EVENT': 'BORED EVENT CHANCE',
            'CONTEXT_HISTORY': 'CONTEXT HISTORY EVENT COUNT',
            'CONTEXT_HISTORY_DIARY': 'CONTEXT HISTORY DIARY EVENT COUNT',
            'CONTEXT_HISTORY_DYNAMIC_PROFILE': 'CONTEXT HISTORY DYNAMIC PROFILE EVENT COUNT'
        };
        const normalizedKey = String(key).toUpperCase();
        if (customLabels[normalizedKey]) {
            return customLabels[normalizedKey];
        }
        return String(key)
            .split('@')
            .map(part => part.replace(/_/g, ' '))
            .join(' -> ');
    }
    
    // Render overrides list
    function renderOverridesList() {
        const list = el('ovr-list');
        if (!list) return;

        if (IS_PROFILE_MODE) {
            renderProfileOverrides(list);
            syncOverrides();
            return;
        }
        
        const keys = Object.keys(overridesState);
        if (keys.length === 0) {
            const emptyMsg = IS_PROFILE_MODE
                ? 'No overrides set. Click "Add Override" to customize settings for this profile.'
                : 'No overrides set. Click "Add Override" to customize settings for this NPC.';
            list.innerHTML = `<div class="${PREFIX}ovr-empty">${emptyMsg}</div>`;
            return;
        }
        
        list.innerHTML = keys.map(key => {
            if (key == "middle_term_memory")
                return;
            const value = overridesState[key];
            const displayValue = (Array.isArray(value) || (value && typeof value === 'object'))
                ? JSON.stringify(value)
                : String(value);
            return `
                <div class="${PREFIX}ovr-item" data-key="${escapeHtml(key)}">
                    <div class="${PREFIX}ovr-icon">${getIcon(key)}</div>
                    <div class="${PREFIX}ovr-info">
                        <div class="${PREFIX}ovr-key">${escapeHtml(getLabel(key))}</div>
                        <div class="${PREFIX}ovr-value">${escapeHtml(displayValue)}</div>
                    </div>
                    <div class="${PREFIX}ovr-actions">
                        <button type="button" class="btn-${PREFIX}ovr btn-edit-${PREFIX}ovr" data-key="${escapeHtml(key)}">Edit</button>
                        <button type="button" class="btn-${PREFIX}ovr danger btn-remove-${PREFIX}ovr" data-key="${escapeHtml(key)}">×</button>
                    </div>
                </div>
            `;
        }).join('');
        
        // Rebind event listeners
        list.querySelectorAll(`.btn-edit-${PREFIX}ovr`).forEach(btn => {
            btn.addEventListener('click', function() {
                editOverride(this.getAttribute('data-key'));
            });
        });
        
        list.querySelectorAll(`.btn-remove-${PREFIX}ovr`).forEach(btn => {
            btn.addEventListener('click', function() {
                removeOverride(this.getAttribute('data-key'));
            });
        });
        
        syncOverrides();
    }

    // Profile overrides stay visible so users can enable and edit them without a secondary modal.
    function renderProfileOverrides(list) {
        const categories = {};
        for (const [key, definition] of Object.entries(SCHEMA)) {
            if (RESERVED_KEYS.includes(key)) continue;
            const category = definition.category || 'Other';
            if (!categories[category]) categories[category] = [];
            categories[category].push([key, definition]);
        }

        list.innerHTML = Object.entries(categories).map(([category, settings]) => `
            <section class="prof-ovr-category">
                <h3 class="prof-ovr-category-title">${escapeHtml(category)}</h3>
                <div class="prof-ovr-category-settings">
                    ${settings.map(([key, definition]) => renderProfileOverrideItem(key, definition)).join('')}
                </div>
            </section>
        `).join('');

        list.querySelectorAll('.prof-ovr-inline-item').forEach((item) => {
            const key = item.dataset.key;
            const definition = SCHEMA[key] || {};
            const toggle = item.querySelector('.prof-ovr-enabled');
            const input = item.querySelector('.prof-ovr-inline-input');
            if (!toggle || !input) return;

            toggle.addEventListener('change', () => {
                item.classList.toggle('enabled', toggle.checked);
                input.disabled = !toggle.checked;
                if (toggle.checked) {
                    overridesState[key] = readProfileOverrideValue(input, definition.type);
                } else {
                    delete overridesState[key];
                }
                syncOverrides();
            });

            input.addEventListener('change', () => {
                if (!toggle.checked) return;
                overridesState[key] = readProfileOverrideValue(input, definition.type);
                syncOverrides();
            });
            input.addEventListener('input', () => {
                if (!toggle.checked || input.tagName === 'SELECT' || input.type === 'checkbox') return;
                overridesState[key] = readProfileOverrideValue(input, definition.type);
                syncOverrides();
            });
        });
    }

    function renderProfileOverrideItem(key, definition) {
        const enabled = Object.prototype.hasOwnProperty.call(overridesState, key);
        const inheritedValue = definition.global_value ?? '';
        const value = enabled ? overridesState[key] : inheritedValue;
        const description = String(definition.description || '').replace(/<[^>]*>/g, '');
        return `
            <div class="prof-ovr-inline-item${enabled ? ' enabled' : ''}" data-key="${escapeHtml(key)}">
                <div class="prof-ovr-inline-info">
                    <div class="prof-ovr-inline-name"><span>${getIcon(key)}</span><span>${escapeHtml(getLabel(key))}</span></div>
                    ${description ? `<div class="prof-ovr-inline-description">${escapeHtml(description)}</div>` : ''}
                    <div class="prof-ovr-inherited-value">Global value: ${escapeHtml(formatProfileOverrideValue(inheritedValue))}</div>
                </div>
                <div class="prof-ovr-inline-controls">
                    <label class="prof-ovr-toggle"><input type="checkbox" class="prof-ovr-enabled" ${enabled ? 'checked' : ''}> Override</label>
                    ${profileOverrideInput(key, definition, value, enabled)}
                </div>
            </div>
        `;
    }

    function profileOverrideInput(key, definition, value, enabled) {
        const type = definition.type || 'string';
        const disabled = enabled ? '' : ' disabled';
        if (type === 'boolean') {
            const checked = value === true || value === 1 || String(value).toLowerCase() === 'true' || String(value) === '1';
            return `<input type="checkbox" class="prof-ovr-inline-input" ${checked ? 'checked' : ''}${disabled}>`;
        }
        if (type === 'select' && Array.isArray(definition.values)) {
            const labels = definition.value_labels || {};
            return `<select class="prof-ovr-inline-input"${disabled}><option value="">-- Select --</option>${definition.values.map((option) => `<option value="${escapeHtml(option)}" ${String(value) === String(option) ? 'selected' : ''}>${escapeHtml(labels[option] || displayNames[option] || option)}</option>`).join('')}</select>`;
        }
        if (type === 'longstring') {
            return `<textarea class="prof-ovr-inline-input" rows="3"${disabled}>${escapeHtml(value ?? '')}</textarea>`;
        }
        const inputType = type === 'integer' || type === 'number' ? 'number' : 'text';
        const step = type === 'integer' ? ' step="1"' : (type === 'number' ? ' step="any"' : '');
        return `<input type="${inputType}" class="prof-ovr-inline-input" value="${escapeHtml(value ?? '')}"${step}${disabled}>`;
    }

    function readProfileOverrideValue(input, type) {
        if (type === 'boolean') return input.checked;
        if (type === 'integer') return input.value === '' ? '' : parseInt(input.value, 10);
        if (type === 'number') return input.value === '' ? '' : parseFloat(input.value);
        return input.value;
    }

    function formatProfileOverrideValue(value) {
        if (value === '' || value === null || typeof value === 'undefined') return 'Not set';
        const rendered = Array.isArray(value) || typeof value === 'object' ? JSON.stringify(value) : String(value);
        return rendered.length > 180 ? `${rendered.slice(0, 177)}...` : rendered;
    }
    
    // Sync overrides to form
    function syncOverrides() {
        if (IS_NPC_MODE) {
            syncToTextarea();
        } else if (IS_PROFILE_MODE) {
            syncToMetadata();
        }
    }
    
    // Sync to textarea (NPC mode)
    function syncToTextarea() {
        const textarea = document.querySelector(`textarea[name="${FIELD_NAME}"]`);
        if (!textarea) return;
        
        const merged = {...overridesState, ...SYSTEM_FIELDS};
        textarea.value = JSON.stringify(merged);
        
        if (rawJsonEditor && typeof rawJsonEditor.set === 'function') {
            try {
                rawJsonEditor.set({json: merged});
            } catch (e) {
                console.error('Failed to update raw JSON editor:', e);
            }
        }
    }
    
    // Sync to metadata via hidden inputs (Profile mode)
    function syncToMetadata() {
        const container = document.querySelector(`.${PREFIX}ovr-container`);
        if (!container) return;
        
        container.querySelectorAll('input[name^="meta_vis["]').forEach(el => el.remove());
        
        const allKeys = Array.from(new Set([
            ...Object.keys(SCHEMA).filter((key) => !RESERVED_KEYS.includes(key)),
            ...Object.keys(CURRENT_DATA || {}),
            ...Object.keys(overridesState || {})
        ])).filter((key) => !RESERVED_KEYS.includes(key));
        for (const key of allKeys) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `meta_vis[${key}]`;
            input.value = overridesState.hasOwnProperty(key) ? String(overridesState[key]) : '';
            container.appendChild(input);
        }
    }
    
    // Open add modal
    function openAddModal() {
        editingKey = null;
        selectedSetting = null;
        el('ovr-modal-title').textContent = 'Add Override';
        el('ovr-select-stage').style.display = 'block';
        el('ovr-value-stage').style.display = 'none';
        el('ovr-modal-save').style.display = 'none';
        el('ovr-modal-back').style.display = 'none';
        
        if (IS_NPC_MODE) {
            const searchInput = el('ovr-search');
            if (searchInput) searchInput.value = '';
        }
        
        renderSettingsList();
        el('ovr-modal').style.display = 'flex';
    }
    
    // Edit existing override
    function editOverride(key) {
        editingKey = key;
        selectedSetting = key;
        el('ovr-modal-title').textContent = 'Edit Override';
        el('ovr-select-stage').style.display = 'none';
        el('ovr-value-stage').style.display = 'block';
        el('ovr-modal-save').style.display = 'block';
        el('ovr-modal-back').style.display = 'none';
        renderValueForm(key, overridesState[key]);
        el('ovr-modal').style.display = 'flex';
    }
    
    // Remove override
    function removeOverride(key) {
        if (!confirm(`Remove override for "${key.toUpperCase()}"?`)) return;
        delete overridesState[key];
        renderOverridesList();
    }
    
    // Render settings list
    function renderSettingsList(filter = '') {
        const list = el('ovr-settings-list');
        if (!list) return;
        
        const filterLower = filter.toLowerCase();
        
        // Group by category if NPC mode
        if (IS_NPC_MODE) {
            const categories = {};
            for (const [key, schema] of Object.entries(SCHEMA)) {
                const category = schema.category || 'Other';
                if (!categories[category]) categories[category] = [];
                categories[category].push(key);
            }
            
            let hasResults = false;
            let html = '';
            
            for (const [category, keys] of Object.entries(categories)) {
                const filteredKeys = keys.filter(key => {
                    if (!filter) return true;
                    const label = getLabel(key).toLowerCase();
                    const desc = (SCHEMA[key].description || '').toLowerCase();
                    return label.includes(filterLower) || desc.includes(filterLower) || key.toLowerCase().includes(filterLower);
                });
                
                if (filteredKeys.length === 0) continue;
                
                hasResults = true;
                html += `<div style="margin-top:${html?'16px':'0'}; margin-bottom:8px; color:rgb(242, 124, 17); font-weight:700; font-size:14px;">${category}</div>`;
                
                filteredKeys.forEach(key => {
                    const schema = SCHEMA[key];
                    const desc = (schema.description || '').substring(0, 120);
                    html += `
                        <div class="${PREFIX}ovr-setting-option" data-key="${escapeHtml(key)}">
                            <div class="${PREFIX}ovr-setting-name">${getIcon(key)} ${escapeHtml(getLabel(key))}</div>
                            <div class="${PREFIX}ovr-setting-desc">${escapeHtml(desc)}${desc.length >= 120 ? '...' : ''}</div>
                        </div>
                    `;
                });
            }
            
            if (!hasResults) {
                list.innerHTML = `<div class="${PREFIX}ovr-empty">No settings found${filter ? ' matching "' + escapeHtml(filter) + '"' : ''}</div>`;
                return;
            }
            
            list.innerHTML = html;
        } else {
            // Profile mode - simple list
            let html = '';
            for (const [key, schema] of Object.entries(SCHEMA)) {
                const desc = (schema.description || '').substring(0, 120);
                html += `
                    <div class="${PREFIX}ovr-setting-option" data-key="${escapeHtml(key)}">
                        <div class="${PREFIX}ovr-setting-name">${getIcon(key)} ${escapeHtml(getLabel(key))}</div>
                        <div class="${PREFIX}ovr-setting-desc">${escapeHtml(desc)}${desc.length >= 120 ? '...' : ''}</div>
                    </div>
                `;
            }
            list.innerHTML = html;
        }
        
        // Bind click events
        list.querySelectorAll(`.${PREFIX}ovr-setting-option`).forEach(opt => {
            opt.addEventListener('click', function() {
                selectSetting(this.getAttribute('data-key'));
            });
        });
    }
    
    // Select a setting
    function selectSetting(key) {
        selectedSetting = key;
        el('ovr-select-stage').style.display = 'none';
        el('ovr-value-stage').style.display = 'block';
        el('ovr-modal-save').style.display = 'block';
        el('ovr-modal-back').style.display = 'block';
        renderValueForm(key, overridesState[key]);
    }
    
    // Render value form
    function renderValueForm(key, currentValue) {
        const form = el('ovr-value-form');
        if (!form) return;
        
        const schema = SCHEMA[key] || {};
        const type = schema.type || 'string';
        const desc = (schema.description || '').replace(/<[^>]*>/g, '');
        const values = schema.values || [];
        const valueLabels = schema.value_labels || {};
        
        let inputHtml = '';
        let validationHint = '';
        
        if (type === 'boolean') {
            const checked = (currentValue === true || currentValue === 'true' || currentValue === 1) ? 'checked' : '';
            inputHtml = `
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="${PREFIX}ovr-value-input" ${checked} style="transform: scale(1.5);">
                    <span style="color: #e9efff;">Enabled</span>
                </label>
            `;
        } else if (type === 'select' && values.length > 0) {
            inputHtml = `
                <select id="${PREFIX}ovr-value-input" class="${PREFIX}ovr-input" required>
                    <option value="">-- Select --</option>
                    ${values.map(v => `<option value="${escapeHtml(v)}" ${String(currentValue) === String(v) ? 'selected' : ''}>${escapeHtml(valueLabels[v] || displayNames[v] || v)}</option>`).join('')}
                </select>
            `;
            validationHint = `<div style="color:#9fb1c9; font-size:11px; margin-top:4px;">Valid options: ${values.map(v => valueLabels[v] || displayNames[v] || v).join(', ')}</div>`;
        } else if (type === 'integer') {
            const val = currentValue ?? '';
            inputHtml = `<input type="number" id="${PREFIX}ovr-value-input" class="${PREFIX}ovr-input" value="${escapeHtml(val)}" placeholder="Enter integer" step="1" required>`;
            validationHint = `<div style="color:#9fb1c9; font-size:11px; margin-top:4px;">Must be a whole number</div>`;
        } else if (type === 'number') {
            const val = currentValue ?? '';
            inputHtml = `<input type="number" id="${PREFIX}ovr-value-input" class="${PREFIX}ovr-input" value="${escapeHtml(val)}" placeholder="Enter number" step="any" required>`;
            validationHint = `<div style="color:#9fb1c9; font-size:11px; margin-top:4px;">Must be a valid number</div>`;
        } else if (type === 'longstring') {
            inputHtml = `<textarea id="${PREFIX}ovr-value-input" class="${PREFIX}ovr-input" rows="6" placeholder="Enter value" required>${escapeHtml(currentValue ?? '')}</textarea>`;
        } else {
            inputHtml = `<input type="text" id="${PREFIX}ovr-value-input" class="${PREFIX}ovr-input" value="${escapeHtml(currentValue ?? '')}" placeholder="Enter value" required>`;
        }
        
        form.innerHTML = `
            <div class="${PREFIX}ovr-input-group">
                <label class="${PREFIX}ovr-input-label">${getIcon(key)} ${escapeHtml(getLabel(key))}</label>
                <div style="color:#9fb1c9; font-size:12px; margin-bottom:8px;">${escapeHtml(desc)}</div>
                ${inputHtml}
                ${validationHint}
            </div>
        `;
    }
    
    // Save override
    function saveOverride() {
        if (!selectedSetting) return;
        
        const schema = SCHEMA[selectedSetting] || {};
        const type = schema.type || 'string';
        const input = el('ovr-value-input');
        if (!input) return;
        
        let value = null;
        let isValid = true;
        let errorMsg = '';
        
        if (type === 'boolean') {
            value = input.checked;
        } else if (type === 'select') {
            value = input.value;
            if (value === '') {
                isValid = false;
                errorMsg = 'Please select a value';
            }
        } else if (type === 'integer') {
            value = parseInt(input.value, 10);
            if (isNaN(value)) {
                isValid = false;
                errorMsg = 'Please enter a valid integer';
            }
        } else if (type === 'number') {
            value = parseFloat(input.value);
            if (isNaN(value)) {
                isValid = false;
                errorMsg = 'Please enter a valid number';
            }
        } else {
            value = input.value.trim();
            if (value === '' && input.hasAttribute('required')) {
                isValid = false;
                errorMsg = 'This field is required';
            }
        }
        
        if (!isValid) {
            alert(errorMsg);
            return;
        }
        
        overridesState[selectedSetting] = value;
        renderOverridesList();
        closeModal();
    }
    
    // Close modal
    function closeModal() {
        el('ovr-modal').style.display = 'none';
        selectedSetting = null;
        editingKey = null;
    }
    
    // Escape HTML
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }
    
    // Initialize
    function init() {
        renderOverridesList();
        
        // Bind events
        const addBtn = document.getElementById(`btn-add-${PREFIX}ovr`);
        if (addBtn) addBtn.addEventListener('click', openAddModal);
        
        const closeBtn = el('ovr-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        
        const cancelBtn = el('ovr-modal-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        
        const saveBtn = el('ovr-modal-save');
        if (saveBtn) saveBtn.addEventListener('click', saveOverride);
        
        const backBtn = el('ovr-modal-back');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                el('ovr-select-stage').style.display = 'block';
                el('ovr-value-stage').style.display = 'none';
                el('ovr-modal-save').style.display = 'none';
                el('ovr-modal-back').style.display = 'none';
                selectedSetting = null;
            });
        }
        
        if (IS_NPC_MODE) {
            const searchInput = el('ovr-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    renderSettingsList(this.value);
                });
            }
            
            // Advanced JSON toggle
            const jsonToggle = document.getElementById('advanced-json-toggle');
            const jsonContent = document.getElementById('advanced-json-content');
            if (jsonToggle && jsonContent) {
                jsonToggle.addEventListener('click', function() {
                    const isVisible = jsonContent.style.display === 'block';
                    jsonContent.style.display = isVisible ? 'none' : 'block';
                    this.textContent = isVisible ? '▼ Advanced: Raw JSON Editor (click to expand)' : '▲ Advanced: Raw JSON Editor (click to collapse)';
                    
                    if (!isVisible && !rawJsonEditor) {
                        initRawJsonEditor();
                    }
                });
            }
        }
        
        // Close modal on backdrop click
        const modal = el('ovr-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        }
    }
    
    // Initialize raw JSON editor (NPC mode only)
    async function initRawJsonEditor() {
        try {
            const { createJSONEditor } = await import('https://cdn.jsdelivr.net/npm/vanilla-jsoneditor/standalone.js');
            const textarea = document.querySelector(`textarea[name="${FIELD_NAME}"]`);
            const initialContent = textarea ? JSON.parse(textarea.value || '{}') : {};
            
            rawJsonEditor = createJSONEditor({
                target: document.getElementById('extended_data_raw_editor'),
                props: {
                    content: { json: initialContent },
                    onChange: (updatedContent) => {
                        try {
                            const newData = updatedContent.json || {};
                            const newOverrides = {};
                            for (const key in newData) {
                                if (!RESERVED_KEYS.includes(key)) {
                                    newOverrides[key] = newData[key];
                                }
                            }
                            overridesState = newOverrides;
                            renderOverridesList();
                        } catch (e) {
                            console.error('JSON editor update error:', e);
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Failed to initialize raw JSON editor:', e);
        }
    }
    
    // Export sync functions
    if (IS_NPC_MODE) {
        window.syncExtendedDataOverrides = syncToTextarea;
    } else if (IS_PROFILE_MODE) {
        window.syncProfileGlobalOverrides = syncToMetadata;
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

