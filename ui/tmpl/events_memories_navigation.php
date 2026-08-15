<?php
$eventsMemoriesActiveTab = $eventsMemoriesActiveTab ?? ($activeTab ?? 'eventlog');
$eventsMemoriesWebRoot = rtrim((string)($webRoot ?? ''), '/');
$eventsMemoriesUiRoot = $eventsMemoriesWebRoot . '/ui';

$eventsMemoriesGroups = [
    [
        'key' => 'activity-logs',
        'label' => 'Activity & Logs',
        'aria' => 'Activity and log pages',
        'tabs' => [
            ['key' => 'eventlog', 'label' => 'Events', 'icon' => '&#x1F4DD;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=eventlog'],
            ['key' => 'responselog', 'label' => 'AI Responses', 'icon' => '&#x1F4AC;', 'href' => $eventsMemoriesUiRoot . '/ai-response.php', 'external' => true],
            ['key' => 'adventure', 'label' => 'Adventure Log', 'icon' => '&#x1F4C6;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=adventure'],
        ],
    ],
    [
        'key' => 'memories-records',
        'label' => 'Memories & Records',
        'aria' => 'Memory and record pages',
        'tabs' => [
            ['key' => 'memory', 'label' => 'Memories', 'icon' => '&#x1F9E0;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=memory'],
            ['key' => 'diaries', 'label' => 'CHIM Diaries', 'icon' => '&#x1F4D4;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=diaries'],
            ['key' => 'books', 'label' => 'Books', 'icon' => '&#x1F4DA;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=books'],
            ['key' => 'soulgaze', 'label' => 'Soulgaze Gallery', 'icon' => '&#x1F5BC;&#xFE0F;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=soulgaze'],
        ],
    ],
    [
        'key' => 'world-quests',
        'label' => 'World & Quests',
        'aria' => 'World and quest pages',
        'tabs' => [
            ['key' => 'quests', 'label' => 'Active Quests', 'icon' => '&#x1F3AF;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=quests'],
            ['key' => 'questgen', 'label' => 'AI Quest Manager', 'icon' => '&#x1F9ED;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=questgen'],
            ['key' => 'backgroundlife', 'label' => 'Background Life', 'icon' => '&#x1F5FA;&#xFE0F;', 'href' => $eventsMemoriesUiRoot . '/events-memories.php?tab=backgroundlife'],
        ],
    ],
];
?>
<div class="config-navigation events-memories-navigation" aria-label="Roleplay sections">
    <div class="tab-groups">
        <?php foreach ($eventsMemoriesGroups as $group): ?>
            <?php $groupTabKeys = array_column($group['tabs'], 'key'); ?>
            <section class="tab-group <?php echo in_array($eventsMemoriesActiveTab, $groupTabKeys, true) ? 'active' : ''; ?>" data-category="<?php echo htmlspecialchars($group['key'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="tab-group-label"><?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="tab-buttons" role="tablist" aria-label="<?php echo htmlspecialchars($group['aria'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($group['tabs'] as $tab): ?>
                        <?php $isActive = $eventsMemoriesActiveTab === $tab['key']; ?>
                        <a
                            class="tab-button <?php echo $isActive ? 'active' : ''; ?>"
                            href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-tab="<?php echo htmlspecialchars($tab['key'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo empty($tab['external']) ? 'data-local-tab="true"' : ''; ?>
                            <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                        ><span class="tab-icon" aria-hidden="true"><?php echo $tab['icon']; ?></span><span><?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
