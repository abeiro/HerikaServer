<?php

if (!isset($activeTab, $webRoot) || !function_exists('chimTtsPronunciationBoolean')) {
    return;
}

// Prepare the global and Oghma-scoped rows for the TTS Studio view.
$pronRows = (isset($ttsPronunciationRows) && is_array($ttsPronunciationRows)) ? $ttsPronunciationRows : [];
$pronTags = (isset($ttsPronunciationTags) && is_array($ttsPronunciationTags)) ? $ttsPronunciationTags : [];
$pronFilter = isset($ttsPronunciationFilter) ? trim((string)$ttsPronunciationFilter) : '';
$pronPostAction = $webRoot . '/ui/xtts_clone.php?tab=pronunciations';
$pronBuiltinRows = [];
$pronCustomRows = [];
foreach ($pronRows as $pronRow) {
    if (chimTtsPronunciationBoolean($pronRow['is_builtin'] ?? false)) {
        $pronBuiltinRows[] = $pronRow;
    } else {
        $pronCustomRows[] = $pronRow;
    }
}
?>

<div class="tab-content pron-section <?php echo $activeTab === 'pronunciations' ? 'active' : ''; ?>">
    <div class="content-section full-width-section">
        <h1>Pronunciations</h1>
        <p>Rewrite how the TTS engine says a word without changing anything the player reads. These entries apply to every TTS connector.</p>
        <ul class="pron-intro-list">
            <li><strong>Audio only:</strong> subtitles and saved dialogue keep the original spelling &mdash; only the text sent to the voice engine is rewritten.</li>
            <li><strong>Blank Oghma tags:</strong> the entry applies globally, to every speaking NPC.</li>
            <li><strong>Tagged entries:</strong> the entry only applies when the speaking NPC has a matching Oghma knowledge tag, so a pronunciation can be scoped to one faction, region, or questline.</li>
            <li><strong>Built-in entries</strong> are always global and cannot be deleted, but any of them can be disabled.</li>
        </ul>
    </div>

    <div class="content-section full-width-section">
        <h1>Add Custom Pronunciation</h1>
        <p>Original text is matched as a whole term. Leave Oghma tags blank to apply the entry to every NPC.</p>

        <form action="<?php echo $pronPostAction; ?>" method="post" class="pron-cols pron-add-row">
            <input type="hidden" name="action" value="save_tts_pronunciation">
            <div>
                <label class="pron-label" for="pron-add-source">Original</label>
                <input class="pron-field" type="text" id="pron-add-source" name="source_text"
                       maxlength="120" required autocomplete="off" spellcheck="false"
                       placeholder="Jorrvaskr">
            </div>
            <div>
                <label class="pron-label" for="pron-add-spoken">Spoken version</label>
                <input class="pron-field" type="text" id="pron-add-spoken" name="spoken_text"
                       maxlength="240" required autocomplete="off" spellcheck="false"
                       placeholder="Yorvaskr">
            </div>
            <div>
                <label class="pron-label" for="pron-add-tags">Oghma tags (optional)</label>
                <input class="pron-field" type="text" id="pron-add-tags" name="oghma_tags"
                       maxlength="512" autocomplete="off" spellcheck="false"
                       list="pron-tag-options" placeholder="companions, whiterun"
                       aria-describedby="pron-add-tags-help">
                <span id="pron-add-tags-help" class="pron-count">Comma-separated. Blank applies to every NPC.</span>
            </div>
            <div class="pron-toggle">
                <input type="checkbox" id="pron-add-enabled" name="enabled" value="1" checked>
                <label class="pron-label pron-toggle-label" for="pron-add-enabled">Enabled</label>
            </div>
            <div class="pron-actions">
                <button type="submit" class="action-button upload-csv pron-btn">Add Entry</button>
            </div>
        </form>

        <datalist id="pron-tag-options">
            <?php foreach ($pronTags as $pronTagOption): ?>
                <option value="<?php echo htmlspecialchars((string)$pronTagOption); ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <div class="content-section full-width-section">
        <h1>Custom Pronunciations</h1>

        <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php" method="get" class="pron-toolbar">
            <input type="hidden" name="tab" value="pronunciations">
            <div class="pron-toolbar-field">
                <label class="pron-label" for="pron-tag-filter">Filter by Oghma tag</label>
                <select class="pron-field" id="pron-tag-filter" name="oghma_tag">
                    <option value="" <?php echo $pronFilter === '' ? 'selected' : ''; ?>>All tags</option>
                    <?php foreach ($pronTags as $pronTagOption): ?>
                        <?php $pronTagOption = (string)$pronTagOption; ?>
                        <option value="<?php echo htmlspecialchars($pronTagOption); ?>" <?php echo $pronFilter === $pronTagOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pronTagOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="action-button edit pron-btn">Apply Filter</button>
            <?php if ($pronFilter !== ''): ?>
                <a class="pron-clear-filter" href="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=pronunciations">Clear filter</a>
            <?php endif; ?>
        </form>

        <p class="pron-count">
            <?php echo count($pronCustomRows); ?> custom <?php echo count($pronCustomRows) === 1 ? 'entry' : 'entries'; ?><?php echo $pronFilter !== '' ? ' tagged &quot;' . htmlspecialchars($pronFilter) . '&quot;' : ''; ?>.
        </p>

        <div class="pron-grid">
            <div class="pron-cols pron-head" aria-hidden="true">
                <span>Original</span>
                <span>Spoken Version</span>
                <span>Oghma Tags</span>
                <span>Enabled</span>
                <span>Actions</span>
            </div>

            <?php if (empty($pronCustomRows)): ?>
                <p class="pron-empty">
                    <?php if ($pronFilter !== ''): ?>
                        No custom entries use the tag &quot;<?php echo htmlspecialchars($pronFilter); ?>&quot;. Choose <strong>All tags</strong> to see every entry.
                    <?php else: ?>
                        No custom pronunciations yet. Add one above to override how a word is spoken.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <?php foreach ($pronCustomRows as $pronRow): ?>
                    <?php
                    $pronId = (string)($pronRow['id'] ?? '');
                    $pronKey = preg_replace('/[^A-Za-z0-9_-]/', '', $pronId);
                    $pronEnabled = chimTtsPronunciationBoolean($pronRow['enabled'] ?? false);
                    $pronSource = (string)($pronRow['source_text'] ?? '');
                    ?>
                    <form action="<?php echo $pronPostAction; ?>" method="post"
                          class="pron-cols pron-row <?php echo $pronEnabled ? '' : 'is-disabled'; ?>">
                        <input type="hidden" name="action" value="save_tts_pronunciation">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($pronId); ?>">
                        <div>
                            <label class="pron-label" for="pron-source-<?php echo $pronKey; ?>">Original</label>
                            <input class="pron-field" type="text" id="pron-source-<?php echo $pronKey; ?>"
                                   name="source_text" value="<?php echo htmlspecialchars($pronSource); ?>"
                                   maxlength="120" required autocomplete="off" spellcheck="false">
                        </div>
                        <div>
                            <label class="pron-label" for="pron-spoken-<?php echo $pronKey; ?>">Spoken version</label>
                            <input class="pron-field" type="text" id="pron-spoken-<?php echo $pronKey; ?>"
                                   name="spoken_text" value="<?php echo htmlspecialchars((string)($pronRow['spoken_text'] ?? '')); ?>"
                                   maxlength="240" required autocomplete="off" spellcheck="false">
                        </div>
                        <div>
                            <label class="pron-label" for="pron-tags-<?php echo $pronKey; ?>">Oghma tags</label>
                            <input class="pron-field" type="text" id="pron-tags-<?php echo $pronKey; ?>"
                                   name="oghma_tags" value="<?php echo htmlspecialchars((string)($pronRow['oghma_tags'] ?? '')); ?>"
                                   maxlength="512" autocomplete="off" spellcheck="false"
                                   list="pron-tag-options" placeholder="Blank = every NPC">
                        </div>
                        <div class="pron-toggle">
                            <input type="checkbox" id="pron-enabled-<?php echo $pronKey; ?>" name="enabled" value="1"
                                   aria-label="Enable <?php echo htmlspecialchars($pronSource); ?>"
                                   <?php echo $pronEnabled ? 'checked' : ''; ?>>
                            <label class="pron-label pron-toggle-label" for="pron-enabled-<?php echo $pronKey; ?>">Enabled</label>
                        </div>
                        <div class="pron-actions">
                            <button type="submit" id="pron-save-<?php echo $pronKey; ?>" class="action-button upload-csv pron-btn">Save</button>
                            <button type="submit" form="pron-delete-form-<?php echo $pronKey; ?>"
                                    class="action-button delete pron-btn"
                                    onclick="return confirm('Delete the custom pronunciation for <?php echo htmlspecialchars(str_replace(["\\", "'"], ["\\\\", "\\'"], $pronSource), ENT_QUOTES); ?>?');">Delete</button>
                        </div>
                    </form>
                    <form action="<?php echo $pronPostAction; ?>" method="post"
                          id="pron-delete-form-<?php echo $pronKey; ?>" class="pron-hidden-form">
                        <input type="hidden" name="action" value="delete_tts_pronunciation">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($pronId); ?>">
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-section full-width-section">
        <h1>Built-in Pronunciations</h1>
        <p>Shipped defaults for common lore names. These are always global &mdash; they ignore Oghma tags and cannot be deleted. Disable any entry you would rather replace with a custom entry above.</p>

        <div class="pron-grid">
            <div class="pron-cols pron-head" aria-hidden="true">
                <span>Original</span>
                <span>Spoken Version</span>
                <span>Scope</span>
                <span>Enabled</span>
                <span>Actions</span>
            </div>

            <?php if (empty($pronBuiltinRows)): ?>
                <p class="pron-empty">No built-in pronunciations are available.</p>
            <?php else: ?>
                <?php foreach ($pronBuiltinRows as $pronIndex => $pronRow): ?>
                    <?php
                    $pronId = (string)($pronRow['id'] ?? '');
                    $pronKey = 'b' . preg_replace('/[^A-Za-z0-9_-]/', '', $pronId) . '-' . $pronIndex;
                    $pronEnabled = chimTtsPronunciationBoolean($pronRow['enabled'] ?? false);
                    ?>
                    <form action="<?php echo $pronPostAction; ?>" method="post"
                          class="pron-cols pron-row <?php echo $pronEnabled ? '' : 'is-disabled'; ?>">
                        <input type="hidden" name="action" value="toggle_tts_pronunciation">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($pronId); ?>">
                        <div class="pron-static"><?php echo htmlspecialchars((string)($pronRow['source_text'] ?? '')); ?></div>
                        <div class="pron-static"><?php echo htmlspecialchars((string)($pronRow['spoken_text'] ?? '')); ?></div>
                        <div><span class="pron-badge">Always global</span></div>
                        <div class="pron-toggle">
                            <input type="checkbox" id="pron-enabled-<?php echo $pronKey; ?>" name="enabled" value="1"
                                   aria-label="Enable <?php echo htmlspecialchars((string)($pronRow['source_text'] ?? '')); ?>"
                                   <?php echo $pronEnabled ? 'checked' : ''; ?>>
                            <label class="pron-label pron-toggle-label" for="pron-enabled-<?php echo $pronKey; ?>">Enabled</label>
                        </div>
                        <div class="pron-actions">
                            <button type="submit" id="pron-apply-<?php echo $pronKey; ?>" class="action-button edit pron-btn">Apply</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
