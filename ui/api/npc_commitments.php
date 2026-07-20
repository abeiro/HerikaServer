<?php

error_reporting(E_ERROR);
header('Content-Type: application/json');

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, ['load_general_settings' => false]);
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_commitments.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php');

function chimCommitmentsApiReply(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $npcId = (int)($_REQUEST['npc_id'] ?? 0);
    if ($npcId <= 0) {
        chimCommitmentsApiReply(['success' => false, 'error' => 'Invalid NPC'], 400);
    }

    $npc = (new NpcMaster())->getById($npcId);
    if (empty($npc['npc_name'])) {
        chimCommitmentsApiReply(['success' => false, 'error' => 'NPC not found'], 404);
    }

    $actorName = trim((string)$npc['npc_name']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $operation = strtolower(trim((string)($_POST['operation'] ?? '')));
        $outcome = trim((string)($_POST['outcome'] ?? ''));
        $gametsRow = $GLOBALS['db']->fetchOne('SELECT COALESCE(MAX(gamets), 0) AS gamets FROM eventlog');
        $currentGamets = (int)($gametsRow['gamets'] ?? 0);

        if (!in_array($operation, ['completed', 'failed', 'cancelled'], true) || $taskId <= 0) {
            chimCommitmentsApiReply(['success' => false, 'error' => 'Invalid task operation'], 400);
        }

        $result = chimCommitmentSetStatus($actorName, $taskId, $operation, $outcome, $currentGamets);
        if (empty($result['ok'])) {
            chimCommitmentsApiReply(['success' => false, 'error' => $result['error'] ?? 'Task update failed'], 409);
        }
    }

    $tasks = chimCommitmentGetAll($actorName);
    foreach ($tasks as &$task) {
        $task['id'] = (int)$task['id'];
        $task['created_gamets'] = (int)$task['created_gamets'];
        $task['due_gamets'] = (int)$task['due_gamets'];
        $task['repeat_interval_gamets'] = (int)$task['repeat_interval_gamets'];
        $task['occurrence_count'] = (int)$task['occurrence_count'];
        $task['due_label'] = $task['due_gamets'] > 0
            ? convert_gamets2skyrim_long_date($task['due_gamets'])
            : '';
        $task['repeat_hours'] = $task['repeat_interval_gamets'] > 0
            ? round($task['repeat_interval_gamets'] * 0.0000024, 2)
            : 0;
    }
    unset($task);

    chimCommitmentsApiReply([
        'success' => true,
        'npc_id' => $npcId,
        'npc_name' => $actorName,
        'tasks' => $tasks,
    ]);
} catch (Throwable $e) {
    chimCommitmentsApiReply(['success' => false, 'error' => $e->getMessage()], 500);
}
