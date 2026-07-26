<?php

/**
 * Local systems harness for long-running CHIM integration tests.
 *
 * The browser only creates/stops runs. The background service advances one
 * state transition per tick so Skyrim and Apache are never blocked by polling.
 */

if (!function_exists('chimHarnessActiveStatuses')) {
    function chimHarnessActiveStatuses(): array
    {
        return ['created', 'preflight', 'provisioning', 'running', 'stopping', 'restoring'];
    }
}

if (!function_exists('chimHarnessTerminalStatuses')) {
    function chimHarnessTerminalStatuses(): array
    {
        return ['completed', 'cancelled', 'failed'];
    }
}

if (!function_exists('chimHarnessFinalStatus')) {
    function chimHarnessFinalStatus(array $run): string
    {
        return trim((string)($run['error'] ?? '')) !== '' ? 'failed' : 'completed';
    }
}

if (!function_exists('chimHarnessDecodeJson')) {
    function chimHarnessDecodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('chimHarnessEncodeJson')) {
    function chimHarnessEncodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : '{}';
    }
}

if (!function_exists('chimHarnessScenarios')) {
    function chimHarnessScenarios(): array
    {
        static $scenarios = null;
        if (is_array($scenarios)) {
            return $scenarios;
        }

        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chim_harness_scenarios.json';
        $decoded = @json_decode((string)@file_get_contents($path), true);
        $scenarios = is_array($decoded) ? $decoded : [];
        return $scenarios;
    }
}

if (!function_exists('chimHarnessExpandGeneratedActors')) {
    function chimHarnessExpandGeneratedActors(array $scenario, int $runId): array
    {
        $actors = [];
        foreach (($scenario['generated'] ?? []) as $actor) {
            if (!is_array($actor) || empty($actor['key']) || empty($actor['name'])) {
                continue;
            }
            $actor['name'] = str_replace('{RUN}', str_pad((string)$runId, 4, '0', STR_PAD_LEFT), $actor['name']);
            $actors[] = $actor;
        }
        return $actors;
    }
}

if (!function_exists('chimHarnessNormalizeDuration')) {
    function chimHarnessNormalizeDuration($minutes): int
    {
        return max(5, min(480, intval($minutes)));
    }
}

if (!function_exists('chimHarnessNormalizeBglTriggerHours')) {
    function chimHarnessNormalizeBglTriggerHours($hours): float
    {
        $value = is_numeric($hours) ? floatval($hours) : 24.0;
        return max(1.0, min(24.0, $value));
    }
}

if (!function_exists('chimHarnessNormalizeRefId')) {
    function chimHarnessNormalizeRefId($refId): string
    {
        $value = strtoupper(trim((string)$refId));
        $value = preg_replace('/^0X/', '', $value);
        if (!is_string($value) || !preg_match('/^[0-9A-F]{1,8}$/', $value)) {
            return '';
        }
        return str_pad($value, 8, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('chimHarnessIsPrivateRequest')) {
    function chimHarnessIsPrivateRequest(?string $address = null): bool
    {
        $address = $address ?? (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (in_array($address, ['127.0.0.1', '::1'], true)) {
            return true;
        }
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            && filter_var($address, FILTER_VALIDATE_IP) !== false;
    }
}

if (!function_exists('chimHarnessEnsureSchema')) {
    function chimHarnessEnsureSchema(): bool
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return false;
        }
        $existing = $db->fetchOne("SELECT to_regclass('public.chim_harness_run') AS table_name");
        if (!empty($existing['table_name'])) {
            return true;
        }
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'database_schema'
            . DIRECTORY_SEPARATOR . 'chim_systems_harness.sql';
        return is_readable($path) && $db->execQuery(file_get_contents($path)) !== false;
    }
}

if (!function_exists('chimHarnessEvent')) {
    function chimHarnessEvent(
        int $runId,
        string $stage,
        string $message,
        array $data = [],
        string $level = 'info',
        ?int $actorId = null,
        ?int $gamets = null
    ): void {
        $db = $GLOBALS['db'];
        $db->insert('chim_harness_event', [
            'run_id' => $runId,
            'actor_id' => $actorId,
            'localts' => time(),
            'gamets' => $gamets,
            'stage' => $stage,
            'level' => $level,
            'message' => $message,
            'data' => chimHarnessEncodeJson($data),
        ]);
    }
}

if (!function_exists('chimHarnessGetActiveRun')) {
    function chimHarnessGetActiveRun(): array
    {
        $statuses = "'" . implode("','", chimHarnessActiveStatuses()) . "'";
        return $GLOBALS['db']->fetchOne(
            "SELECT * FROM chim_harness_run WHERE status IN ({$statuses}) ORDER BY id DESC LIMIT 1"
        );
    }
}

if (!function_exists('chimHarnessGetRun')) {
    function chimHarnessGetRun(int $runId): array
    {
        return $GLOBALS['db']->fetchOne("SELECT * FROM chim_harness_run WHERE id=" . intval($runId) . " LIMIT 1");
    }
}

if (!function_exists('chimHarnessGetActors')) {
    function chimHarnessGetActors(int $runId): array
    {
        return $GLOBALS['db']->fetchAll(
            "SELECT * FROM chim_harness_actor WHERE run_id=" . intval($runId) . " ORDER BY id"
        );
    }
}

if (!function_exists('chimHarnessGetEvents')) {
    function chimHarnessGetEvents(int $runId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $GLOBALS['db']->fetchAll(
            "SELECT * FROM chim_harness_event WHERE run_id=" . intval($runId)
            . " ORDER BY localts DESC, id DESC LIMIT {$limit}"
        );
    }
}

if (!function_exists('chimHarnessGetRecentRuns')) {
    function chimHarnessGetRecentRuns(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $GLOBALS['db']->fetchAll("SELECT * FROM chim_harness_run ORDER BY id DESC LIMIT {$limit}");
    }
}

if (!function_exists('chimHarnessQueueRoleCommand')) {
    function chimHarnessQueueRoleCommand(string $command, string $tag = 'systems-harness'): void
    {
        $GLOBALS['db']->insert('responselog', [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => 'rolecommand|' . $command,
            'tag' => $tag,
        ]);
    }
}

if (!function_exists('chimHarnessNpcSnapshot')) {
    function chimHarnessNpcSnapshot(array $npc): array
    {
        $extended = chimHarnessDecodeJson($npc['extended_data'] ?? null);
        return [
            'npc_id' => intval($npc['id'] ?? 0),
            'npc_name' => (string)($npc['npc_name'] ?? ''),
            'refid' => chimHarnessNormalizeRefId($npc['refid'] ?? ''),
            'profile_id' => isset($npc['profile_id']) ? intval($npc['profile_id']) : null,
            'lock_profile' => isset($npc['lock_profile']) ? intval($npc['lock_profile']) : null,
            'metadata' => chimHarnessDecodeJson($npc['metadata'] ?? null),
            'extended_data' => $extended,
            'was_background_enabled' => !empty($extended['background_life_enabled']),
        ];
    }
}

if (!function_exists('chimHarnessEvidenceBaseline')) {
    function chimHarnessEvidenceBaseline(): array
    {
        $row = $GLOBALS['db']->fetchOne(
            "SELECT
                COALESCE((SELECT max(rowid) FROM bgl_history), 0) AS bgl_rowid,
                COALESCE((SELECT max(rowid) FROM actions_issued), 0) AS action_rowid,
                COALESCE((SELECT max(rowid) FROM memory), 0) AS memory_rowid,
                COALESCE((SELECT max(rowid) FROM memory_summary), 0) AS summary_rowid,
                COALESCE((SELECT max(rowid) FROM audit_request), 0) AS request_rowid,
                COALESCE((SELECT max(rowid) FROM responselog), 0) AS response_rowid,
                COALESCE((SELECT max(gamets) FROM eventlog), 0) AS gamets"
        );
        return array_map('intval', is_array($row) ? $row : []);
    }
}

if (!function_exists('chimHarnessStartRun')) {
    function chimHarnessStartRun(
        string $scenarioKey,
        string $mode,
        int $durationMinutes,
        array $existingNpcIds = []
    ): array {
        if (!chimHarnessEnsureSchema()) {
            return ['ok' => false, 'message' => 'Harness schema is unavailable.'];
        }
        if (chimHarnessGetActiveRun()) {
            return ['ok' => false, 'message' => 'Another harness run is already active.'];
        }

        $scenarios = chimHarnessScenarios();
        if (!isset($scenarios[$scenarioKey])) {
            return ['ok' => false, 'message' => 'Unknown harness scenario.'];
        }
        $mode = 'live';
        $durationMinutes = chimHarnessNormalizeDuration($durationMinutes);
        $existingNpcIds = array_values(array_unique(array_filter(array_map('intval', $existingNpcIds))));
        $existingNpcIds = array_slice($existingNpcIds, 0, 6);

        if ($scenarioKey === 'existing_soak' && !$existingNpcIds) {
            return ['ok' => false, 'message' => 'Select at least one existing NPC for this scenario.'];
        }

        $now = time();
        $config = [
            'duration_minutes' => $durationMinutes,
            'existing_npc_ids' => $existingNpcIds,
            'created_by' => 'local-control-panel',
            'max_actors' => 8,
            'metrics_interval_seconds' => 15,
            'bgl_trigger_hours' => chimHarnessNormalizeBglTriggerHours(
                $scenarios[$scenarioKey]['bgl_trigger_hours'] ?? 24
            ),
        ];
        $runId = $GLOBALS['db']->insertReturningId('chim_harness_run', [
            'name' => (string)($scenarios[$scenarioKey]['label'] ?? $scenarioKey),
            'status' => 'created',
            'mode' => $mode,
            'scenario' => $scenarioKey,
            'config' => chimHarnessEncodeJson($config),
            'snapshot' => chimHarnessEncodeJson(['baseline' => chimHarnessEvidenceBaseline()]),
            'metrics' => '{}',
            'created_at' => $now,
        ]);
        if ($runId <= 0) {
            return ['ok' => false, 'message' => 'Could not create harness run.'];
        }

        foreach (chimHarnessExpandGeneratedActors($scenarios[$scenarioKey], $runId) as $actor) {
            $GLOBALS['db']->insert('chim_harness_actor', [
                'run_id' => $runId,
                'actor_key' => (string)$actor['key'],
                'actor_name' => (string)$actor['name'],
                'source' => 'generated',
                'status' => 'pending',
                'config' => chimHarnessEncodeJson($actor),
                'snapshot' => '{}',
                'metrics' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($existingNpcIds as $npcId) {
            $npc = $GLOBALS['db']->fetchOne("SELECT * FROM core_npc_master WHERE id={$npcId} LIMIT 1");
            if (!$npc || empty($npc['npc_name']) || chimHarnessNormalizeRefId($npc['refid'] ?? '') === '') {
                chimHarnessEvent($runId, 'create', "Skipped NPC id {$npcId}: no usable live RefID.", [], 'warn');
                continue;
            }
            $snapshot = chimHarnessNpcSnapshot($npc);
            $GLOBALS['db']->insert('chim_harness_actor', [
                'run_id' => $runId,
                'actor_key' => 'existing_' . $npcId,
                'actor_name' => (string)$npc['npc_name'],
                'source' => 'existing',
                'npc_id' => $npcId,
                'refid' => $snapshot['refid'],
                'status' => 'pending',
                'config' => '{}',
                'snapshot' => chimHarnessEncodeJson($snapshot),
                'metrics' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $actorCount = intval(($GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n FROM chim_harness_actor WHERE run_id={$runId}"
        ))['n'] ?? 0);
        if ($actorCount === 0) {
            $GLOBALS['db']->updateRow('chim_harness_run', [
                'status' => 'failed',
                'error' => 'The scenario did not produce any valid actors.',
                'ended_at' => $now,
            ], "id={$runId}");
            return ['ok' => false, 'message' => 'The scenario did not produce any valid actors.'];
        }

        chimHarnessEvent($runId, 'create', "Created {$mode} run with {$actorCount} actor(s).", $config);
        return ['ok' => true, 'run_id' => $runId, 'message' => 'Harness run created.'];
    }
}

if (!function_exists('chimHarnessApplyRunSettings')) {
    function chimHarnessApplyRunSettings(array $run): array
    {
        $snapshot = chimHarnessDecodeJson($run['snapshot'] ?? null);
        if (!empty($snapshot['test_settings']['attempted'])) {
            return $run;
        }

        require_once __DIR__ . DIRECTORY_SEPARATOR . 'settings.php';
        $setting = $GLOBALS['db']->fetchOne(
            "SELECT value, description FROM public.general_settings WHERE id='BGL_TRIGGER_HOURS' LIMIT 1"
        );
        $config = chimHarnessDecodeJson($run['config'] ?? null);
        $testHours = chimHarnessNormalizeBglTriggerHours($config['bgl_trigger_hours'] ?? 24);
        $snapshot['test_settings'] = [
            'attempted' => true,
            'BGL_TRIGGER_HOURS' => [
                'existed' => !empty($setting),
                'value' => $setting['value'] ?? null,
                'description' => $setting['description'] ?? null,
                'test_value' => $testHours,
            ],
        ];

        $applied = chimSetGeneralSetting(
            'BGL_TRIGGER_HOURS',
            $testHours,
            isset($setting['description']) ? (string)$setting['description'] : null
        );
        $snapshot['test_settings']['applied'] = $applied;
        $GLOBALS['db']->updateRow('chim_harness_run', [
            'snapshot' => chimHarnessEncodeJson($snapshot),
            'last_tick_at' => time(),
        ], 'id=' . intval($run['id']));
        $run['snapshot'] = chimHarnessEncodeJson($snapshot);

        chimHarnessEvent(
            intval($run['id']),
            'settings',
            $applied
                ? "Temporarily set Background Life trigger time to {$testHours} game-hours."
                : 'Could not apply the temporary Background Life trigger time; continuing with the user setting.',
            ['bgl_trigger_hours' => $testHours],
            $applied ? 'info' : 'warn'
        );
        return $run;
    }
}

if (!function_exists('chimHarnessTemporaryActionRestoreEntries')) {
    function chimHarnessTemporaryActionRestoreEntries(array $snapshot): array
    {
        $entries = [];
        foreach (($snapshot['temporary_action_overrides'] ?? []) as $codeName => $override) {
            if (!is_array($override)
                || empty($override['restore_required'])
                || !empty($override['restored_at'])
                || ($override['table'] ?? '') !== 'core_action_custom'
                || intval($override['id'] ?? 0) <= 0
                || !is_array($override['metadata'] ?? null)) {
                continue;
            }

            $entries[(string)$codeName] = [
                'table' => 'core_action_custom',
                'id' => intval($override['id']),
                'metadata' => $override['metadata'],
            ];
        }

        return $entries;
    }
}

if (!function_exists('chimHarnessApplyTemporaryActionOverrides')) {
    function chimHarnessApplyTemporaryActionOverrides(array $run): array
    {
        $snapshot = chimHarnessDecodeJson($run['snapshot'] ?? null);
        if (!empty($snapshot['temporary_action_override_setup']['attempted_at'])) {
            return $run;
        }

        $rows = $GLOBALS['db']->fetchAll(
            "SELECT id, code_name, metadata
             FROM public.core_action_custom
             WHERE lower(code_name) IN ('moveto', 'travelto')"
        );
        $overrides = [];
        foreach ($rows as $row) {
            $metadata = chimHarnessDecodeJson($row['metadata'] ?? null);
            $confirmationRequired = strtolower(trim(strval(
                $metadata['custom_config']['confirmation_required'] ?? false
            )));
            if (!in_array($confirmationRequired, ['1', 'true', 't', 'yes', 'on'], true)) {
                continue;
            }

            $codeName = trim((string)($row['code_name'] ?? ''));
            $id = intval($row['id'] ?? 0);
            if ($codeName === '' || $id <= 0) {
                continue;
            }

            if (empty($snapshot['temporary_action_overrides'][$codeName])) {
                $snapshot['temporary_action_overrides'][$codeName] = [
                    'table' => 'core_action_custom',
                    'id' => $id,
                    'metadata' => $metadata,
                    'restore_required' => true,
                ];
            }
            $overrides[$codeName] = [
                'id' => $id,
                'metadata' => $metadata,
            ];
        }

        $snapshot['temporary_action_override_setup'] = [
            'attempted_at' => time(),
            'actions' => array_keys($overrides),
        ];
        $GLOBALS['db']->updateRow('chim_harness_run', [
            'snapshot' => chimHarnessEncodeJson($snapshot),
            'last_tick_at' => time(),
        ], 'id=' . intval($run['id']));

        $disabled = [];
        foreach ($overrides as $codeName => $override) {
            $metadata = $override['metadata'];
            $metadata['custom_config']['confirmation_required'] = false;
            $updated = $GLOBALS['db']->updateRow(
                'core_action_custom',
                ['metadata' => chimHarnessEncodeJson($metadata)],
                'id=' . $override['id']
            ) !== false;
            if ($updated) {
                $disabled[] = $codeName;
            } else {
                chimHarnessEvent(
                    intval($run['id']),
                    'settings',
                    "Could not temporarily disable confirmation for {$codeName}.",
                    [],
                    'error'
                );
            }
        }

        $snapshot['temporary_action_override_setup']['disabled'] = $disabled;
        $GLOBALS['db']->updateRow('chim_harness_run', [
            'snapshot' => chimHarnessEncodeJson($snapshot),
            'last_tick_at' => time(),
        ], 'id=' . intval($run['id']));
        $run['snapshot'] = chimHarnessEncodeJson($snapshot);

        if ($disabled) {
            chimHarnessEvent(
                intval($run['id']),
                'settings',
                'Temporarily disabled movement action confirmation for unattended testing.',
                ['actions' => $disabled]
            );
        }

        return $run;
    }
}

if (!function_exists('chimHarnessRestoreRunSettings')) {
    function chimHarnessRestoreRunSettings(array $run): void
    {
        $snapshot = chimHarnessDecodeJson($run['snapshot'] ?? null);
        $runId = intval($run['id']);
        $snapshotChanged = false;

        if (!empty($snapshot['test_settings']['applied'])
            && empty($snapshot['test_settings']['restored_at'])) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'settings.php';
            $original = $snapshot['test_settings']['BGL_TRIGGER_HOURS'] ?? [];
            if (!empty($original['existed'])) {
                $restored = chimSetGeneralSetting(
                    'BGL_TRIGGER_HOURS',
                    $original['value'] ?? 24,
                    array_key_exists('description', $original) ? $original['description'] : null
                );
            } else {
                $restored = $GLOBALS['db']->execQuery(
                    "DELETE FROM public.general_settings WHERE id='BGL_TRIGGER_HOURS'"
                ) !== false;
            }

            if ($restored) {
                $snapshot['test_settings']['restored_at'] = time();
                $snapshotChanged = true;
                chimHarnessEvent(
                    $runId,
                    'restore',
                    'Restored the original Background Life trigger time.'
                );
            } else {
                chimHarnessEvent(
                    $runId,
                    'restore',
                    'Could not restore the original Background Life trigger time.',
                    [],
                    'error'
                );
            }
        }

        foreach (chimHarnessTemporaryActionRestoreEntries($snapshot) as $codeName => $override) {
            $restored = $GLOBALS['db']->updateRow(
                $override['table'],
                ['metadata' => chimHarnessEncodeJson($override['metadata'])],
                'id=' . $override['id']
            ) !== false;

            if ($restored) {
                $snapshot['temporary_action_overrides'][$codeName]['restored_at'] = time();
                $snapshotChanged = true;
                chimHarnessEvent(
                    $runId,
                    'restore',
                    "Restored the original {$codeName} action configuration."
                );
            } else {
                chimHarnessEvent(
                    $runId,
                    'restore',
                    "Could not restore the original {$codeName} action configuration.",
                    [],
                    'error'
                );
            }
        }

        if ($snapshotChanged) {
            $GLOBALS['db']->updateRow('chim_harness_run', [
                'snapshot' => chimHarnessEncodeJson($snapshot),
                'last_tick_at' => time(),
            ], 'id=' . $runId);
        }
    }
}

if (!function_exists('chimHarnessRequestStop')) {
    function chimHarnessRequestStop(int $runId): array
    {
        $run = chimHarnessGetRun($runId);
        if (!$run || in_array($run['status'] ?? '', chimHarnessTerminalStatuses(), true)) {
            return ['ok' => false, 'message' => 'Run is not active.'];
        }
        $GLOBALS['db']->updateRow('chim_harness_run', [
            'status' => 'stopping',
            'last_tick_at' => time(),
        ], "id={$runId}");
        chimHarnessEvent($runId, 'stop', 'Stop and restore requested by user.');
        return ['ok' => true, 'message' => 'Stop requested. Cleanup will run on the next service tick.'];
    }
}

if (!function_exists('chimHarnessSetRunStatus')) {
    function chimHarnessSetRunStatus(int $runId, string $status, array $extra = []): void
    {
        $data = array_merge(['status' => $status, 'last_tick_at' => time()], $extra);
        $GLOBALS['db']->updateRow('chim_harness_run', $data, "id={$runId}");
    }
}

if (!function_exists('chimHarnessSetActorStatus')) {
    function chimHarnessSetActorStatus(int $actorId, string $status, array $extra = []): void
    {
        $data = array_merge(['status' => $status, 'updated_at' => time()], $extra);
        $GLOBALS['db']->updateRow('chim_harness_actor', $data, "id={$actorId}");
    }
}

if (!function_exists('chimHarnessGameHeartbeat')) {
    function chimHarnessGameHeartbeat(): array
    {
        $row = $GLOBALS['db']->fetchOne(
            "SELECT max(localts) AS localts, max(gamets) AS gamets FROM eventlog"
        );
        $localts = intval($row['localts'] ?? 0);
        return [
            'localts' => $localts,
            'gamets' => intval($row['gamets'] ?? 0),
            'age_seconds' => $localts > 0 ? max(0, time() - $localts) : PHP_INT_MAX,
            'fresh' => $localts > 0 && (time() - $localts) <= 120,
        ];
    }
}

if (!function_exists('chimHarnessConfigureNpc')) {
    function chimHarnessConfigureNpc(array $npc, array $actorConfig, int $runId, bool $enableBackground = true): void
    {
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';
        $npcMaster = new NpcMaster();
        $extended = $npcMaster->getExtendedData($npc);
        $extended['background_life_commands'] = true;
        $extended['background_life_enabled'] = $enableBackground;
        $extended['background_life_player_unattached'] = true;
        $extended['background_life_last_updated'] = 0;
        $extended['middle_term_enabled'] = 1;
        $extended['chim_harness_run_id'] = $runId;
        $metadata = $npcMaster->getMetadata($npc);
        $metadata['gps_track'] = true;
        $metadata['chim_harness_run_id'] = $runId;

        if (array_key_exists('background', $actorConfig)) {
            $npc['core'] = ($actorConfig['name'] ?? $npc['npc_name']) . '. '
                . ($actorConfig['gender'] ?? '') . ' ' . ($actorConfig['class'] ?? '') . ' '
                . ($actorConfig['race'] ?? '');
            $npc['npc_static_bio'] = (string)$actorConfig['background'];
            $npc['speechstyle'] = (string)($actorConfig['speech_style'] ?? '');
            $npc['goals'] = (string)($actorConfig['goal'] ?? '');
            $npc['lock_profile'] = null;
        }
        $npc = $npcMaster->setMetadata($npc, $metadata);
        $npc = $npcMaster->setExtendedData($npc, $extended);
        $npcMaster->updateByArray($npc);
    }
}

if (!function_exists('chimHarnessProvisionGeneratedActor')) {
    function chimHarnessProvisionGeneratedActor(array $actor, int $runId): void
    {
        $actorId = intval($actor['id']);
        $config = chimHarnessDecodeJson($actor['config'] ?? null);
        $name = (string)$actor['actor_name'];
        $status = (string)$actor['status'];

        if ($status === 'pending') {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'rolemaster_helpers.php';
            npcProfileBase(
                $name,
                (string)($config['class'] ?? 'farmer'),
                (string)($config['race'] ?? 'Nord'),
                (string)($config['gender'] ?? 'male'),
                (string)($config['location'] ?? 'nearby'),
                '0',
                ['disposition' => (string)($config['disposition'] ?? 'neutral')]
            );
            chimHarnessSetActorStatus($actorId, 'spawn_queued', [
                'metrics' => chimHarnessEncodeJson(['spawn_queued_at' => time()]),
            ]);
            chimHarnessEvent($runId, 'spawn', "Queued generated actor {$name}.", $config, 'info', $actorId);
            return;
        }

        if ($status !== 'spawn_queued') {
            return;
        }

        $safeName = $GLOBALS['db']->escape($name);
        $npc = $GLOBALS['db']->fetchOne(
            "SELECT * FROM core_npc_master WHERE npc_name='{$safeName}' AND refid IS NOT NULL AND btrim(refid)<>'' LIMIT 1"
        );
        if (!$npc) {
            $metrics = chimHarnessDecodeJson($actor['metrics'] ?? null);
            $queuedAt = intval($metrics['spawn_queued_at'] ?? time());
            if (time() - $queuedAt > 120) {
                chimHarnessSetActorStatus($actorId, 'failed', ['error' => 'Spawn did not produce a RefID within 120 seconds.']);
                chimHarnessEvent($runId, 'spawn', "Timed out waiting for {$name}.", [], 'error', $actorId);
            }
            return;
        }

        $refId = chimHarnessNormalizeRefId($npc['refid']);
        chimHarnessConfigureNpc($npc, $config, $runId, false);
        chimHarnessQueueRoleCommand("TrackBackgroundNPC@0x{$refId}@{$name}@{$runId}");
        chimHarnessSetActorStatus($actorId, 'tracking', [
            'npc_id' => intval($npc['id']),
            'refid' => $refId,
            'metrics' => chimHarnessEncodeJson([
                'spawn_queued_at' => intval(chimHarnessDecodeJson($actor['metrics'] ?? null)['spawn_queued_at'] ?? time()),
                'track_queued_at' => time(),
            ]),
        ]);
        chimHarnessEvent($runId, 'track', "Queued RefID tracking for {$name}.", ['refid' => $refId], 'info', $actorId);
    }
}

if (!function_exists('chimHarnessProvisionExistingActor')) {
    function chimHarnessProvisionExistingActor(array $actor, int $runId): void
    {
        if (($actor['status'] ?? '') !== 'pending') {
            return;
        }
        $refId = chimHarnessNormalizeRefId($actor['refid'] ?? '');
        if ($refId === '') {
            chimHarnessSetActorStatus(intval($actor['id']), 'failed', ['error' => 'Existing NPC has no valid RefID.']);
            return;
        }
        chimHarnessQueueRoleCommand(
            "TrackBackgroundNPC@0x{$refId}@{$actor['actor_name']}@{$runId}"
        );
        chimHarnessSetActorStatus(intval($actor['id']), 'tracking', [
            'metrics' => chimHarnessEncodeJson(['track_queued_at' => time()]),
        ]);
        chimHarnessEvent(
            $runId,
            'track',
            "Queued temporary BgL tracking for {$actor['actor_name']}.",
            ['refid' => $refId],
            'info',
            intval($actor['id'])
        );
    }
}

if (!function_exists('chimHarnessHasPluginAck')) {
    function chimHarnessHasPluginAck(int $runId, string $stage, string $refId): bool
    {
        $runNeedle = $GLOBALS['db']->escape((string)$runId . '/' . $stage . '/');
        $refNeedle = $GLOBALS['db']->escape(strtoupper($refId));
        $row = $GLOBALS['db']->fetchOne(
            "SELECT 1 FROM eventlog
             WHERE type='harness_status'
               AND data ILIKE '%{$runNeedle}%'
               AND upper(data) LIKE '%{$refNeedle}%'
             ORDER BY rowid DESC LIMIT 1"
        );
        return !empty($row);
    }
}

if (!function_exists('chimHarnessCheckTrackingActor')) {
    function chimHarnessCheckTrackingActor(array $actor, int $runId): void
    {
        if (($actor['status'] ?? '') !== 'tracking') {
            return;
        }
        $actorId = intval($actor['id']);
        $refId = chimHarnessNormalizeRefId($actor['refid'] ?? '');
        if (chimHarnessHasPluginAck($runId, 'tracked', $refId)) {
            if (($actor['source'] ?? '') === 'existing') {
                $npc = $GLOBALS['db']->fetchOne("SELECT * FROM core_npc_master WHERE id=" . intval($actor['npc_id']) . " LIMIT 1");
                if ($npc) {
                    chimHarnessConfigureNpc($npc, ['name' => $actor['actor_name']], $runId, true);
                }
            }
            $metrics = chimHarnessDecodeJson($actor['metrics'] ?? null);
            $metrics['tracked_at'] = time();
            $metrics['track_latency_seconds'] = max(0, time() - intval($metrics['track_queued_at'] ?? time()));
            chimHarnessSetActorStatus($actorId, 'active', ['metrics' => chimHarnessEncodeJson($metrics)]);
            chimHarnessEvent($runId, 'track', "{$actor['actor_name']} acknowledged by CHIM.", [
                'refid' => $refId,
                'latency_seconds' => $metrics['track_latency_seconds'],
            ], 'info', $actorId);
            return;
        }

        $metrics = chimHarnessDecodeJson($actor['metrics'] ?? null);
        if (time() - intval($metrics['track_queued_at'] ?? time()) > 90) {
            chimHarnessSetActorStatus($actorId, 'failed', ['error' => 'CHIM did not acknowledge tracking within 90 seconds.']);
            chimHarnessEvent($runId, 'track', "Timed out tracking {$actor['actor_name']}.", [], 'error', $actorId);
        }
    }
}

if (!function_exists('chimHarnessMetricActorScope')) {
    function chimHarnessMetricActorScope(array $actors): array
    {
        $names = [];
        foreach ($actors as $actor) {
            $name = trim((string)($actor['actor_name'] ?? ''));
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        if (!$names) {
            return [
                'names' => [],
                'memory' => 'FALSE',
                'summaries' => 'FALSE',
            ];
        }

        $quotedNames = array_map(
            static fn(string $name): string => "'" . $GLOBALS['db']->escape($name) . "'",
            $names
        );
        $nameList = implode(', ', $quotedNames);

        return [
            'names' => $names,
            'memory' => "(speaker IN ({$nameList}) OR listener IN ({$nameList}))",
            'summaries' => "string_to_array(trim(both '|' FROM COALESCE(companions, '')), '|') && ARRAY[{$nameList}]::text[]",
        ];
    }
}

if (!function_exists('chimHarnessCollectActorMetrics')) {
    function chimHarnessCollectActorMetrics(array $actor, array $run): array
    {
        $runStartedAt = intval($run['started_at'] ?? $run['created_at'] ?? time());
        $runSnapshot = chimHarnessDecodeJson($run['snapshot'] ?? null);
        $baseline = is_array($runSnapshot['baseline'] ?? null) ? $runSnapshot['baseline'] : [];
        $safeName = $GLOBALS['db']->escape((string)$actor['actor_name']);
        $bgl = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n, max(localts) AS last FROM bgl_history
             WHERE npc='{$safeName}' AND rowid>" . intval($baseline['bgl_rowid'] ?? 0)
        );
        $actions = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n, max(localts) AS last FROM actions_issued
             WHERE actorname='{$safeName}' AND rowid>" . intval($baseline['action_rowid'] ?? 0)
        );
        $memory = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n, max(localts) AS last FROM memory
             WHERE (speaker='{$safeName}' OR listener='{$safeName}')
               AND rowid>" . intval($baseline['memory_rowid'] ?? 0)
        );
        $summaries = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n, max(gamets_truncated) AS last FROM memory_summary
             WHERE '{$safeName}' = ANY(
                       string_to_array(trim(both '|' FROM COALESCE(companions, '')), '|')
                   )
               AND rowid>" . intval($baseline['summary_rowid'] ?? 0)
        );
        $requests = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n, max(created_at) AS last FROM audit_request
             WHERE rowid>" . intval($baseline['request_rowid'] ?? 0) . "
               AND (request ILIKE '%{$safeName}%' OR result ILIKE '%{$safeName}%')"
        );
        $oghma = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n, max(created_at) AS last FROM audit_memory
             WHERE created_at>=to_timestamp({$runStartedAt})
               AND keywords IN (
                   'oghma keyword offered',
                   'oghma keyword already injected from scene context'
               )
               AND (input ILIKE '%{$safeName}%' OR keywords ILIKE '%{$safeName}%')"
        );
        return [
            'bgl_events' => intval($bgl['n'] ?? 0),
            'last_bgl_at' => intval($bgl['last'] ?? 0),
            'actions' => intval($actions['n'] ?? 0),
            'last_action_at' => intval($actions['last'] ?? 0),
            'memory_events' => intval($memory['n'] ?? 0),
            'last_memory_at' => intval($memory['last'] ?? 0),
            'memory_summaries' => intval($summaries['n'] ?? 0),
            'last_summary_gamets' => intval($summaries['last'] ?? 0),
            'llm_requests' => intval($requests['n'] ?? 0),
            'oghma_hits' => intval($oghma['n'] ?? 0),
        ];
    }
}

if (!function_exists('chimHarnessCollectMetrics')) {
    function chimHarnessCollectMetrics(array $run, bool $force = false): array
    {
        $startedAt = intval($run['started_at'] ?? $run['created_at'] ?? time());
        $existingTotals = chimHarnessDecodeJson($run['metrics'] ?? null);
        $config = chimHarnessDecodeJson($run['config'] ?? null);
        $interval = max(5, min(60, intval($config['metrics_interval_seconds'] ?? 15)));
        if (!$force && intval($existingTotals['collected_at'] ?? 0) > time() - $interval) {
            return $existingTotals;
        }
        $runSnapshot = chimHarnessDecodeJson($run['snapshot'] ?? null);
        $baseline = is_array($runSnapshot['baseline'] ?? null) ? $runSnapshot['baseline'] : [];
        $totals = [
            'actors' => 0,
            'active_actors' => 0,
            'failed_actors' => 0,
            'bgl_events' => 0,
            'actions' => 0,
            'memory_events' => 0,
            'memory_summaries' => 0,
            'llm_requests' => 0,
            'oghma_hits' => 0,
            'pending_responses' => 0,
            'heartbeat_age_seconds' => chimHarnessGameHeartbeat()['age_seconds'],
            'collected_at' => time(),
        ];

        $actors = chimHarnessGetActors(intval($run['id']));
        $actorScope = chimHarnessMetricActorScope($actors);
        foreach ($actors as $actor) {
            $metrics = array_merge(
                chimHarnessDecodeJson($actor['metrics'] ?? null),
                chimHarnessCollectActorMetrics($actor, $run)
            );
            $GLOBALS['db']->updateRow('chim_harness_actor', [
                'metrics' => chimHarnessEncodeJson($metrics),
                'updated_at' => time(),
            ], 'id=' . intval($actor['id']));
            $totals['actors']++;
            $totals['active_actors'] += (($actor['status'] ?? '') === 'active') ? 1 : 0;
            $totals['failed_actors'] += (($actor['status'] ?? '') === 'failed') ? 1 : 0;
            foreach (['bgl_events', 'actions'] as $key) {
                $totals[$key] += intval($metrics[$key] ?? 0);
            }
        }

        $memory = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n,
                    count(*) FILTER (WHERE btrim(COALESCE(message, ''))='') AS blank
             FROM memory
             WHERE rowid>" . intval($baseline['memory_rowid'] ?? 0) . "
               AND ({$actorScope['memory']})"
        );
        $memoryDuplicates = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n
             FROM (
                 SELECT 1
                 FROM memory
                 WHERE rowid>" . intval($baseline['memory_rowid'] ?? 0) . "
                   AND ({$actorScope['memory']})
                 GROUP BY speaker, listener, event, message, gamets
                 HAVING count(*)>1
             ) duplicate_groups"
        );
        $summaries = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n,
                    count(*) FILTER (WHERE btrim(COALESCE(summary, ''))='') AS blank
             FROM memory_summary
             WHERE rowid>" . intval($baseline['summary_rowid'] ?? 0) . "
               AND ({$actorScope['summaries']})"
        );
        $summaryDuplicates = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n
             FROM (
                 SELECT 1
                 FROM memory_summary
                 WHERE rowid>" . intval($baseline['summary_rowid'] ?? 0) . "
                   AND ({$actorScope['summaries']})
                 GROUP BY gamets_truncated, companions, classifier, scope, summary
                 HAVING count(*)>1
             ) duplicate_groups"
        );
        $totals['memory_events'] = intval($memory['n'] ?? 0);
        $totals['memory_summaries'] = intval($summaries['n'] ?? 0);
        $totals['blank_memory_rows'] = intval($memory['blank'] ?? 0);
        $totals['duplicate_memory_groups'] = intval($memoryDuplicates['n'] ?? 0);
        $totals['blank_summary_rows'] = intval($summaries['blank'] ?? 0);
        $totals['duplicate_summary_groups'] = intval($summaryDuplicates['n'] ?? 0);
        $totals['data_quality_alerts'] =
            $totals['blank_memory_rows']
            + $totals['duplicate_memory_groups']
            + $totals['blank_summary_rows']
            + $totals['duplicate_summary_groups'];

        $requests = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n FROM audit_request
             WHERE rowid>" . intval($baseline['request_rowid'] ?? 0)
        );
        $totals['llm_requests'] = intval($requests['n'] ?? 0);
        $pending = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n FROM responselog
             WHERE sent=0 AND rowid>" . intval($baseline['response_rowid'] ?? 0)
        );
        $totals['pending_responses'] = intval($pending['n'] ?? 0);
        $oghma = $GLOBALS['db']->fetchOne(
            "SELECT count(*) AS n,
                    count(DISTINCT md5(COALESCE(memory, ''))) AS unique_contexts
             FROM audit_memory
             WHERE created_at>=to_timestamp({$startedAt})
               AND keywords IN (
                   'oghma keyword offered',
                   'oghma keyword already injected from scene context'
               )"
        );
        $totals['oghma_hits'] = intval($oghma['n'] ?? 0);
        $totals['oghma_unique_contexts'] = intval($oghma['unique_contexts'] ?? 0);
        $GLOBALS['db']->updateRow('chim_harness_run', [
            'metrics' => chimHarnessEncodeJson($totals),
            'last_tick_at' => time(),
        ], 'id=' . intval($run['id']));
        return $totals;
    }
}

if (!function_exists('chimHarnessRetainActorAfterRun')) {
    function chimHarnessRetainActorAfterRun(array $actor, int $runId): void
    {
        if (($actor['status'] ?? '') === 'restored') {
            return;
        }

        chimHarnessSetActorStatus(intval($actor['id']), 'restored');
        chimHarnessEvent(
            $runId,
            'retain',
            "Retained {$actor['actor_name']} in Background Life after the run.",
            [],
            'info',
            intval($actor['id'])
        );
    }
}

if (!function_exists('chimHarnessTick')) {
    function chimHarnessTick(): void
    {
        if (!chimHarnessEnsureSchema()) {
            return;
        }
        $lock = $GLOBALS['db']->fetchOne("SELECT pg_try_advisory_lock(43484647) AS acquired");
        if (!in_array(strtolower((string)($lock['acquired'] ?? '')), ['t', 'true', '1'], true)) {
            return;
        }

        try {
            $run = chimHarnessGetActiveRun();
            if (!$run) {
                return;
            }
            $runId = intval($run['id']);
            $status = (string)$run['status'];
            $config = chimHarnessDecodeJson($run['config'] ?? null);

            if ($status === 'created') {
                chimHarnessSetRunStatus($runId, 'preflight');
                chimHarnessEvent($runId, 'preflight', 'Waiting for a recent Skyrim event before provisioning.');
                return;
            }

            if ($status === 'preflight') {
                $heartbeat = chimHarnessGameHeartbeat();
                if (($run['mode'] ?? 'live') === 'live' && !$heartbeat['fresh']) {
                    if (time() - intval($run['created_at']) > 300) {
                        chimHarnessSetRunStatus($runId, 'failed', [
                            'error' => 'No current Skyrim heartbeat was received within five minutes.',
                            'ended_at' => time(),
                        ]);
                        chimHarnessEvent($runId, 'preflight', 'Run failed: Skyrim heartbeat remained stale.', $heartbeat, 'error');
                    }
                    return;
                }
                $run = chimHarnessApplyRunSettings($run);
                chimHarnessSetRunStatus($runId, 'provisioning', ['started_at' => time()]);
                chimHarnessEvent($runId, 'preflight', 'Preflight passed.', $heartbeat);
                return;
            }

            if ($status === 'provisioning') {
                $actors = chimHarnessGetActors($runId);
                foreach ($actors as $actor) {
                    if (($actor['source'] ?? '') === 'generated') {
                        chimHarnessProvisionGeneratedActor($actor, $runId);
                    } else {
                        chimHarnessProvisionExistingActor($actor, $runId);
                    }
                }
                foreach (chimHarnessGetActors($runId) as $actor) {
                    chimHarnessCheckTrackingActor($actor, $runId);
                }
                $counts = $GLOBALS['db']->fetchOne(
                    "SELECT count(*) AS total,
                            count(*) FILTER (WHERE status='active') AS active,
                            count(*) FILTER (WHERE status='failed') AS failed
                     FROM chim_harness_actor WHERE run_id={$runId}"
                );
                $total = intval($counts['total'] ?? 0);
                $done = intval($counts['active'] ?? 0) + intval($counts['failed'] ?? 0);
                if ($total > 0 && $done === $total) {
                    if (intval($counts['active'] ?? 0) === 0) {
                        chimHarnessSetRunStatus($runId, 'stopping', [
                            'error' => 'No actors were successfully activated.',
                        ]);
                        chimHarnessEvent(
                            $runId,
                            'stop',
                            'No actors were successfully activated. Restoring test state before failing the run.',
                            [],
                            'error'
                        );
                    } else {
                        chimHarnessSetRunStatus($runId, 'running');
                        chimHarnessEvent($runId, 'running', 'Soak run is active. Leave Skyrim running normally.');
                    }
                }
                return;
            }

            if ($status === 'running') {
                $run = chimHarnessApplyRunSettings($run);
                $run = chimHarnessApplyTemporaryActionOverrides($run);
                $metrics = chimHarnessCollectMetrics($run);
                $durationSeconds = chimHarnessNormalizeDuration($config['duration_minutes'] ?? 30) * 60;
                if (time() - intval($run['started_at'] ?? time()) >= $durationSeconds) {
                    chimHarnessSetRunStatus($runId, 'stopping');
                    chimHarnessEvent($runId, 'stop', 'Configured soak duration completed.', $metrics);
                }
                return;
            }

            if ($status === 'stopping' || $status === 'restoring') {
                chimHarnessSetRunStatus($runId, 'restoring');
                foreach (chimHarnessGetActors($runId) as $actor) {
                    chimHarnessRetainActorAfterRun($actor, $runId);
                }
                $counts = $GLOBALS['db']->fetchOne(
                    "SELECT count(*) AS total,
                            count(*) FILTER (WHERE status='restored') AS restored
                     FROM chim_harness_actor WHERE run_id={$runId}"
                );
                if (intval($counts['total'] ?? 0) === intval($counts['restored'] ?? -1)) {
                    $run = chimHarnessGetRun($runId);
                    $metrics = chimHarnessCollectMetrics($run, true);
                    chimHarnessRestoreRunSettings($run);
                    $finalStatus = chimHarnessFinalStatus($run);
                    chimHarnessSetRunStatus($runId, $finalStatus, ['ended_at' => time()]);
                    chimHarnessEvent(
                        $runId,
                        $finalStatus === 'failed' ? 'failed' : 'complete',
                        $finalStatus === 'failed'
                            ? 'Run failed. Test NPCs remain in Background Life; temporary settings were restored.'
                            : 'Run completed. Test NPCs remain in Background Life; temporary settings were restored.',
                        $metrics,
                        $finalStatus === 'failed' ? 'error' : 'info'
                    );
                }
            }
        } catch (Throwable $e) {
            $run = isset($run) && is_array($run) ? $run : [];
            $runId = intval($run['id'] ?? 0);
            if ($runId > 0) {
                $currentRun = chimHarnessGetRun($runId);
                $currentStatus = (string)($currentRun['status'] ?? $run['status'] ?? '');
                $snapshot = chimHarnessDecodeJson($currentRun['snapshot'] ?? $run['snapshot'] ?? null);
                $cleanupRequired = in_array(
                    $currentStatus,
                    ['provisioning', 'running', 'stopping', 'restoring'],
                    true
                ) || !empty($snapshot['test_settings']['applied']);
                chimHarnessSetRunStatus(
                    $runId,
                    $cleanupRequired ? 'stopping' : 'failed',
                    array_merge(
                        ['error' => $e->getMessage()],
                        $cleanupRequired ? [] : ['ended_at' => time()]
                    )
                );
                chimHarnessEvent($runId, 'error', $e->getMessage(), [], 'error');
            }
            if (class_exists('Logger')) {
                Logger::error('[SYSTEMS-HARNESS] ' . $e->getMessage());
            }
        } finally {
            $GLOBALS['db']->fetchOne("SELECT pg_advisory_unlock(43484647) AS released");
        }
    }
}
