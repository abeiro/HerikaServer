<?php declare(strict_types=1);

require_once __DIR__.DIRECTORY_SEPARATOR.'DatabaseTestCase.php';

final class NarratorPromptActionTest extends DatabaseTestCase
{
    public function testNarratorInputTextHidesDisabledNarratorPrivateActionsByDefault(): void
    {
        require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'phpunit.class.php';

        $db = new sql();
        $GLOBALS['db'] = $db;

        $GLOBALS['PLAYER_NAME'] = 'RANGROO';
        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        $GLOBALS['IS_NPC'] = false;
        $GLOBALS['DIRECT_NARRATOR_DIALOGUE'] = true;
        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
        $GLOBALS['CORE_LANG'] = '';
        $GLOBALS['EMOTEMOODS'] = 'neutral,assertive';
        $GLOBALS['LANG_LLM_XTTS'] = false;
        $GLOBALS['TTSFUNCTION'] = '';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['INLINE_NARRATION_ENABLED'] = false;
        $GLOBALS['use_emotions_expression'] = false;
        $GLOBALS['OVERRIDE_DIALOGUE_TARGET'] = false;
        $GLOBALS['FEATURES']['MISC']['JSON_DIALOGUE_FORMAT_REORDER'] = false;
        $GLOBALS['gameRequest'] = ['narrator_inputtext', 100, 200, 'give rangroo 100 gold'];
        $gameRequest = $GLOBALS['gameRequest'];

        require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'prompt.includes.php';

        $this->assertStringContainsString('Check #ACTIONS section', $PROMPTS['narrator_inputtext']['cue'][0] ?? '');

        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
        require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'functions'.DIRECTORY_SEPARATOR.'json_response.php';

        $this->assertArrayHasKey('action', $GLOBALS['responseTemplate']);
        $this->assertIsString($GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('SpawnItem', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('SpawnGold', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('SpawnNPC', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('TeleportNPC', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('KillTarget', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('DirectorCommand', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('CreateNewNPC', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: SpawnItem', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: SpawnGold', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: SpawnNPC', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: TeleportNPC', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: KillTarget', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: DirectorCommand', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: CreateNewNPC', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringContainsString('AVAILABLE ACTION: Talk', $GLOBALS['PROMPT_ACTIONS_LIST']);

        $GLOBALS['FUNC_LIST'] = ['Talk'];
        $GLOBALS['PROMPT_ACTIONS_LIST'] = "\n<available_actions_list>\nAVAILABLE ACTION: Talk\n</available_actions_list>";
        $GLOBALS['responseTemplate']['action'] = 'Talk';

        $this->assertTrue(function_exists('chimRefreshJsonResponseState'));
        chimRefreshJsonResponseState();

        $this->assertStringNotContainsString('SpawnItem', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('SpawnGold', $GLOBALS['responseTemplate']['action']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: SpawnItem', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringNotContainsString('AVAILABLE ACTION: SpawnGold', $GLOBALS['PROMPT_ACTIONS_LIST']);
        $this->assertStringContainsString('AVAILABLE ACTION: Talk', $GLOBALS['PROMPT_ACTIONS_LIST']);

        $db->close();
        unset($GLOBALS['db']);
    }

    public function testNarratorCheatmodeKeepsNarratorActionsEnabled(): void
    {
        require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'phpunit.class.php';

        $db = new sql();
        $GLOBALS['db'] = $db;

        $GLOBALS['PLAYER_NAME'] = 'RANGROO';
        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        $GLOBALS['IS_NPC'] = false;
        $GLOBALS['DIRECT_NARRATOR_DIALOGUE'] = true;
        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
        $GLOBALS['CORE_LANG'] = '';
        $GLOBALS['EMOTEMOODS'] = 'neutral,assertive';
        $GLOBALS['LANG_LLM_XTTS'] = false;
        $GLOBALS['TTSFUNCTION'] = '';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['INLINE_NARRATION_ENABLED'] = false;
        $GLOBALS['use_emotions_expression'] = false;
        $GLOBALS['OVERRIDE_DIALOGUE_TARGET'] = false;
        $GLOBALS['FEATURES']['MISC']['JSON_DIALOGUE_FORMAT_REORDER'] = false;
        $GLOBALS['gameRequest'] = ['cheatmode', 100, 200, '<summon an elk>'];
        $db->execQuery("UPDATE public.core_action SET is_activated = TRUE WHERE code_name = 'SpawnNPC'");

        require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'functions'.DIRECTORY_SEPARATOR.'json_response.php';

        $this->assertStringContainsString('SpawnNPC', $GLOBALS['responseTemplate']['action'] ?? '');
        $this->assertStringContainsString('AVAILABLE ACTION: Spawn_NPC', $GLOBALS['PROMPT_ACTIONS_LIST'] ?? '');

        $db->close();
        unset($GLOBALS['db']);
    }

    public function testNarratorSpawnNpcDisplayActionNameResolvesToCodeNameAndPayload(): void
    {
        require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'phpunit.class.php';

        $db = new sql();
        $GLOBALS['db'] = $db;

        $GLOBALS['PLAYER_NAME'] = 'RANGROO';
        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        $GLOBALS['IS_NPC'] = false;
        $GLOBALS['DIRECT_NARRATOR_DIALOGUE'] = true;
        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
        $GLOBALS['CORE_LANG'] = '';
        $GLOBALS['EMOTEMOODS'] = 'neutral,assertive';
        $GLOBALS['LANG_LLM_XTTS'] = false;
        $GLOBALS['TTSFUNCTION'] = '';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['INLINE_NARRATION_ENABLED'] = false;
        $GLOBALS['use_emotions_expression'] = false;
        $GLOBALS['OVERRIDE_DIALOGUE_TARGET'] = false;
        $GLOBALS['FEATURES']['MISC']['JSON_DIALOGUE_FORMAT_REORDER'] = false;
        $GLOBALS['gameRequest'] = ['narrator_inputtext', 100, 200, 'spawn an elk'];
        $db->execQuery("UPDATE public.core_action SET is_activated = TRUE WHERE code_name = 'SpawnNPC'");

        require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'functions'.DIRECTORY_SEPARATOR.'json_response.php';

        $executionContext = buildFunctionExecutionContextFromResponse([
            'action' => 'Spawn_NPC',
            'target' => 'Elk',
            'item' => '',
            'amount' => 1,
        ]);

        $this->assertTrue($executionContext['function_found']);
        $this->assertSame('SpawnNPC', $executionContext['function_code_name']);
        $this->assertIsArray($executionContext['parameter_value']);
        $this->assertSame('Elk', $executionContext['parameter_value']['target'] ?? null);
        $this->assertSame(1, $executionContext['parameter_value']['amount'] ?? null);

        $db->close();
        unset($GLOBALS['db']);
    }

    public function testNarratorSpawnGoldDisplayActionNameResolvesToCodeNameAndPayload(): void
    {
        require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'phpunit.class.php';

        $db = new sql();
        $GLOBALS['db'] = $db;

        $GLOBALS['PLAYER_NAME'] = 'RANGROO';
        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        $GLOBALS['IS_NPC'] = false;
        $GLOBALS['DIRECT_NARRATOR_DIALOGUE'] = true;
        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
        $GLOBALS['CORE_LANG'] = '';
        $GLOBALS['EMOTEMOODS'] = 'neutral,assertive';
        $GLOBALS['LANG_LLM_XTTS'] = false;
        $GLOBALS['TTSFUNCTION'] = '';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['INLINE_NARRATION_ENABLED'] = false;
        $GLOBALS['use_emotions_expression'] = false;
        $GLOBALS['OVERRIDE_DIALOGUE_TARGET'] = false;
        $GLOBALS['FEATURES']['MISC']['JSON_DIALOGUE_FORMAT_REORDER'] = false;
        $GLOBALS['gameRequest'] = ['narrator_inputtext', 100, 200, 'give RANGROO 1000 gold'];
        $db->execQuery("UPDATE public.core_action SET is_activated = TRUE WHERE code_name = 'SpawnGold'");

        require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'functions'.DIRECTORY_SEPARATOR.'json_response.php';

        $executionContext = buildFunctionExecutionContextFromResponse([
            'action' => 'Spawn_Gold',
            'target' => 'RANGROO',
            'item' => '',
            'amount' => 1000,
        ]);

        $this->assertTrue($executionContext['function_found']);
        $this->assertSame('SpawnGold', $executionContext['function_code_name']);
        $this->assertIsArray($executionContext['parameter_value']);
        $this->assertSame('RANGROO', $executionContext['parameter_value']['target'] ?? null);
        $this->assertSame(1000, $executionContext['parameter_value']['amount'] ?? null);

        $db->close();
        unset($GLOBALS['db']);
    }

    public function testTaskActionsExposeAndDecodeActionSpecificParameters(): void
    {
        require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'phpunit.class.php';

        $db = new sql();
        $GLOBALS['db'] = $db;

        $GLOBALS['PLAYER_NAME'] = 'RANGROO';
        $GLOBALS['HERIKA_NAME'] = 'Danica Pure-Spring';
        $GLOBALS['IS_NPC'] = true;
        $GLOBALS['DIRECT_NARRATOR_DIALOGUE'] = false;
        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
        $GLOBALS['CORE_LANG'] = '';
        $GLOBALS['EMOTEMOODS'] = 'neutral,kindly';
        $GLOBALS['LANG_LLM_XTTS'] = false;
        $GLOBALS['TTSFUNCTION'] = '';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['INLINE_NARRATION_ENABLED'] = false;
        $GLOBALS['use_emotions_expression'] = false;
        $GLOBALS['OVERRIDE_DIALOGUE_TARGET'] = false;
        $GLOBALS['FEATURES']['MISC']['JSON_DIALOGUE_FORMAT_REORDER'] = false;
        $GLOBALS['gameRequest'] = ['inputtext', 100, 200, 'create a recurring gate patrol'];
        $db->execQuery("UPDATE public.core_action SET is_activated = TRUE WHERE code_name IN ('CreateTasks', 'ResolveTask', 'CancelTask')");

        require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'functions'.DIRECTORY_SEPARATOR.'json_response.php';

        $this->assertArrayHasKey('action_params', $GLOBALS['responseTemplate']);
        $this->assertArrayHasKey('subject', $GLOBALS['responseTemplate']['action_params']);
        $this->assertArrayHasKey('repeat_every_hours', $GLOBALS['responseTemplate']['action_params']);

        $structuredParameters = $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['action_params']['properties'] ?? [];
        $this->assertArrayHasKey('type', $structuredParameters);
        $this->assertArrayHasKey('due_in_hours', $structuredParameters);
        $this->assertArrayHasKey('task_id', $structuredParameters);

        $executionContext = buildFunctionExecutionContextFromResponse([
            'action' => 'Create_Tasks',
            'target' => '',
            'item' => '',
            'action_params' => [
                'type' => 'other',
                'subject' => 'Check the Riverwood gate',
                'due_in_hours' => 1,
                'repeat_every_hours' => 2,
            ],
        ]);

        $this->assertTrue($executionContext['function_found']);
        $this->assertSame('CreateTasks', $executionContext['function_code_name']);
        $this->assertSame([], $executionContext['missing_required']);
        $this->assertSame('Check the Riverwood gate', $executionContext['parameter_value']['subject'] ?? null);
        $this->assertSame(1.0, $executionContext['parameter_value']['due_in_hours'] ?? null);
        $this->assertSame(2.0, $executionContext['parameter_value']['repeat_every_hours'] ?? null);

        $db->close();
        unset($GLOBALS['db']);
    }
}
