<?php
/**
 * Relationship LLM Logs - View relationship evaluations and changes
 */

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

// Determine web root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// DB connection
$db = $GLOBALS["db"];

$TITLE = "CHIM - Relationship LLM Logs";
$isEmbedded = (isset($_GET['embed']) && $_GET['embed']);

ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: <?php echo $isEmbedded ? '10px' : '40px'; ?>; padding-bottom: 40px; padding-left: 10px; padding-right: 10px; }
footer { display: <?php echo $isEmbedded ? 'none' : 'block'; ?>; }

.rel-log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}
.rel-log-header h1 { margin: 0; font-size: 1.4em; }
.rel-stats { display: flex; gap: 15px; flex-wrap: wrap; }
.rel-stat { background: #2a2a2a; padding: 8px 12px; border-radius: 6px; border: 1px solid #4a4a4a; }
.rel-stat-label { color: #888; font-size: 0.85em; }
.rel-stat-value { color: rgb(242, 124, 17); font-weight: bold; font-size: 1.1em; }

.rel-filters { margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.rel-filters select, .rel-filters input {
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid #666;
    background: #2a2a2a;
    color: #f8f9fa;
}
.btn-base { cursor:pointer; padding:6px 10px; border-radius:4px; border:1px solid #666; }
.btn-primary { background:#204e7a; color:#fff; }
.btn-primary:hover { background:#2a5e8a; }

.rel-log-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
.rel-log-table th { background: #1a1a1a; padding: 10px 8px; text-align: left; border-bottom: 2px solid rgb(242, 124, 17); }
.rel-log-table td { padding: 8px; border-bottom: 1px solid #3a3a3a; vertical-align: top; }
.rel-log-table tr:hover { background: #2a2a2a; }

.rel-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: bold;
}
.rel-type-eval { background: #1e3a5f; color: #60a5fa; }
.rel-type-analyze { background: #3f2f1e; color: #f59e0b; }
.rel-type-npc2npc { background: #1e3f2f; color: #34d399; }

.rel-delta { font-weight: bold; padding: 2px 6px; border-radius: 3px; }
.rel-delta-pos { background: #1e3f1e; color: #4ade80; }
.rel-delta-neg { background: #3f1e1e; color: #f87171; }
.rel-delta-zero { background: #2a2a2a; color: #888; }

.rel-changes { min-width: 300px; }
.rel-change-item { margin: 4px 0; padding: 4px 8px; background: #1a1a1a; border-radius: 4px; font-size: 0.9em; }
.rel-reason { color: #aaa; font-style: italic; font-size: 0.85em; margin-top: 2px; }

.rel-context-toggle {
    cursor: pointer;
    color: rgb(242, 124, 17);
    text-decoration: underline;
    font-size: 0.85em;
    display: inline-block;
    margin-left: 10px;
}
.rel-context-wrapper {
    margin-top: 8px;
}
.rel-context-content {
    display: none;
    margin-top: 8px;
    padding: 10px;
    background: #1a1a1a;
    border-radius: 4px;
    border: 1px solid #3a3a3a;
    max-height: 400px;
    overflow-y: auto;
    font-size: 0.8em;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.4;
}
.rel-context-content.show { display: block; }

.pagination-buttons { margin: 15px 0; display: flex; gap: 10px; }
.no-data { text-align: center; padding: 40px; color: #888; }

.cleanup-section {
    margin: 15px 0;
    padding: 12px;
    background: #2a2a2a;
    border-radius: 8px;
    border: 1px solid #4a4a4a;
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: nowrap;
    width: 100%;
}
.cleanup-section span { white-space: nowrap; }
.btn-danger { background: #7a1e1e; color: #fff; border-color: #5a1515; }
.btn-danger:hover { background: #8a2e2e; }
.btn-warning { background: #a04040; color: #fff; border-color: #804040; }
.btn-warning:hover { background: #b05050; }
.cleanup-confirm {
    display: none;
    background: #3a1a1a;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #7a1e1e;
    margin: 10px 0;
}
.cleanup-confirm.show { display: block; }
</style>

<main>
<?php
// Handle deletion requests
$deleteMessage = '';
if (isset($_POST['delete_action'])) {
    $action = $_POST['delete_action'];

    if ($action === 'delete_all') {
        $deleteQuery = "DELETE FROM audit_request WHERE connector LIKE '%Relationship%'";
        $db->execQuery($deleteQuery);
        $deleteMessage = '✅ All relationship logs deleted.';
    } elseif ($action === 'delete_older') {
        $interval = $_POST['delete_interval'] ?? '1 week';
        // Sanitize interval
        $validIntervals = ['1 hour', '6 hours', '1 day', '3 days', '1 week', '2 weeks', '1 month'];
        if (in_array($interval, $validIntervals)) {
            $deleteQuery = "DELETE FROM audit_request WHERE connector LIKE '%Relationship%' AND created_at < NOW() - INTERVAL '{$interval}'";
            $db->execQuery($deleteQuery);
            $deleteMessage = "✅ Logs older than {$interval} deleted.";
        }
    }
}

$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 50;
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$offset = ($page - 1) * $limit;
$typeFilter = isset($_GET["type"]) ? $_GET["type"] : '';

// Build WHERE clause
$whereClause = "WHERE connector LIKE '%Relationship%'";
if ($typeFilter) {
    $escapedType = $db->escape($typeFilter);
    $whereClause .= " AND request LIKE '%\"type\":\"{$escapedType}%'";
}

// Get stats
$statsQuery = "SELECT COUNT(*) as total FROM audit_request WHERE connector LIKE '%Relationship%'";
$statsResult = $db->fetchOne($statsQuery);
$totalLogs = $statsResult['total'] ?? 0;

// Get recent activity count (last hour)
$recentQuery = "SELECT COUNT(*) as recent FROM audit_request WHERE connector LIKE '%Relationship%' AND created_at > NOW() - INTERVAL '1 hour'";
$recentResult = $db->fetchOne($recentQuery);
$recentLogs = $recentResult['recent'] ?? 0;

// Get logs
$query = "SELECT rowid, created_at, request, result, connector, usage
          FROM audit_request
          {$whereClause}
          ORDER BY created_at DESC
          LIMIT {$limit} OFFSET {$offset}";
$results = $db->fetchAll($query);
?>

<div class="rel-log-header">
    <h1>🔗 Relationship LLM Logs</h1>
    <div class="rel-stats">
        <div class="rel-stat">
            <span class="rel-stat-label">Total Evaluations</span>
            <div class="rel-stat-value"><?php echo number_format($totalLogs); ?></div>
        </div>
        <div class="rel-stat">
            <span class="rel-stat-label">Last Hour</span>
            <div class="rel-stat-value"><?php echo number_format($recentLogs); ?></div>
        </div>
    </div>
</div>

<?php if ($deleteMessage): ?>
<div style="background: #1e3f1e; border: 1px solid #2d5a2d; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; color: #4ade80;">
    <?php echo $deleteMessage; ?>
</div>
<?php endif; ?>

<div class="rel-filters">
    <label>Type:</label>
    <select onchange="filterByType(this.value)">
        <option value="">All Types</option>
        <option value="eval_" <?php echo $typeFilter === 'eval_' ? 'selected' : ''; ?>>Evaluations</option>
        <option value="analyze_" <?php echo $typeFilter === 'analyze_' ? 'selected' : ''; ?>>Analyze (Build with AI)</option>
        <option value="npc2npc_" <?php echo $typeFilter === 'npc2npc_' ? 'selected' : ''; ?>>NPC-to-NPC</option>
    </select>
    <button class="btn-base btn-primary" onclick="window.location.reload()">🔄 Refresh</button>
</div>

<!-- Cleanup Section -->
<?php if ($totalLogs > 0): ?>
<div class="cleanup-section">
    <form method="POST" style="display: inline-flex; gap: 10px; align-items: center;" onsubmit="return confirmDelete('older')">
        <input type="hidden" name="delete_action" value="delete_older">
        <span style="color: #9fb1c9;">Delete logs older than</span>
        <select name="delete_interval" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #666; background: #2a2a2a; color: #f8f9fa;">
            <option value="1 hour">1 hour</option>
            <option value="6 hours">6 hours</option>
            <option value="1 day">1 day</option>
            <option value="3 days">3 days</option>
            <option value="1 week" selected>1 week</option>
            <option value="2 weeks">2 weeks</option>
            <option value="1 month">1 month</option>
        </select>
        <button type="submit" class="btn-base btn-warning" style="white-space: nowrap;">🗑️ Delete Old</button>
    </form>
    <form method="POST" style="display: inline-flex; align-items: center;" onsubmit="return confirmDelete('all')">
        <input type="hidden" name="delete_action" value="delete_all">
        <button type="submit" class="btn-base btn-danger" style="white-space: nowrap;">🗑️ Delete All</button>
    </form>
</div>
<?php endif; ?>

<?php if (empty($results)): ?>
<div class="no-data">
    <p>No relationship evaluations found.</p>
    <p style="font-size: 0.9em;">Make sure RELLLM_CONNECTOR is configured in your profile.</p>
</div>
<?php else: ?>

<div class="pagination-buttons">
    <?php if ($page > 1): ?>
        <button class="btn-base btn-primary" onclick="goToPage(<?php echo $page - 1; ?>)">← Previous</button>
    <?php endif; ?>
    <span style="padding: 6px;">Page <?php echo $page; ?></span>
    <?php if (count($results) >= $limit): ?>
        <button class="btn-base btn-primary" onclick="goToPage(<?php echo $page + 1; ?>)">Next →</button>
    <?php endif; ?>
</div>

<table class="rel-log-table">
    <thead>
        <tr>
            <th style="width: 100px;">Time</th>
            <th style="width: 80px;">Type</th>
            <th style="width: 130px;">NPC</th>
            <th>Changes & Context</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $row):
        $reqData = json_decode($row['request'], true);
        // Success rows carry an "OK|" status prefix (audit-viewer convention); strip before decoding
        $resultRaw = preg_replace('/^OK\|/', '', (string)$row['result']);
        $resultData = json_decode($resultRaw, true);

        // Parse type from request
        $type = $reqData['type'] ?? 'unknown';
        $typeClass = 'rel-type-eval';
        $typeLabel = $type;

        // For NPC-to-NPC, we need to extract both names
        $speakerName = '';
        $listenerName = '';

        if (strpos($type, 'eval_') === 0) {
            $npcName = substr($type, 5);
            $typeLabel = 'eval';
            $typeClass = 'rel-type-eval';
        } elseif (strpos($type, 'analyze_') === 0) {
            $npcName = substr($type, 8);
            $typeLabel = 'analyze';
            $typeClass = 'rel-type-analyze';
        } elseif (strpos($type, 'npc2npc_') === 0) {
            // Format: npc2npc_SpeakerName_ListenerName
            $namesStr = substr($type, 8);
            // Split on underscore, but handle names that might contain underscores
            // Try to find the split point by looking for known NPC name patterns
            $underscorePos = strpos($namesStr, '_');
            if ($underscorePos !== false) {
                $speakerName = substr($namesStr, 0, $underscorePos);
                $listenerName = substr($namesStr, $underscorePos + 1);
            } else {
                $speakerName = $namesStr;
                $listenerName = '';
            }
            $npcName = $speakerName . ' → ' . $listenerName;
            $typeLabel = 'npc2npc';
            $typeClass = 'rel-type-npc2npc';
        } else {
            $npcName = $type;
        }

        // Parse changes from result
        $changes = [];
        if (is_array($resultData) && isset($resultData['changes'])) {
            $changes = $resultData['changes'];
        } elseif (is_string($row['result'])) {
            // Try to parse JSON from result string - handle both formats
            $jsonContent = $resultRaw;

            // Extract JSON from markdown code block if present
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $jsonContent, $matches)) {
                $jsonContent = trim($matches[1]);
            }

            $parsed = json_decode($jsonContent, true);

            if ($parsed) {
                // Standard format with 'changes' key (eval results)
                if (isset($parsed['changes'])) {
                    $changes = $parsed['changes'];
                } else {
                    // NPC-to-NPC format: {speaker/listener or NpcName: {delta, reason}, ...}
                    // Each key that has a 'delta' is a change
                    foreach ($parsed as $target => $data) {
                        if (is_array($data) && isset($data['delta'])) {
                            // Replace "speaker"/"listener" with actual NPC names
                            if ($target === 'speaker' && !empty($speakerName)) {
                                $changes[$speakerName] = $data;
                            } elseif ($target === 'listener' && !empty($listenerName)) {
                                $changes[$listenerName] = $data;
                            } else {
                                $changes[$target] = $data;
                            }
                        }
                    }
                }
            }
        }

        // Get user message for context
        $contextText = '';
        if (isset($reqData['messages'])) {
            foreach ($reqData['messages'] as $msg) {
                if (isset($msg['role']) && $msg['role'] === 'user') {
                    $contextText = $msg['content'] ?? '';
                    break;
                }
            }
        }

        // Format time
        $time = '';
        if (!empty($row['created_at'])) {
            $dt = new DateTime($row['created_at']);
            $time = $dt->format('H:i:s');
            $date = $dt->format('M j');
        }

        $rowId = $row['rowid'];
    ?>
        <tr>
            <td>
                <div style="font-weight: bold;"><?php echo $time; ?></div>
                <div style="color: #888; font-size: 0.85em;"><?php echo $date ?? ''; ?></div>
            </td>
            <td><span class="rel-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars($typeLabel); ?></span></td>
            <td><?php echo htmlspecialchars($npcName); ?></td>
            <td class="rel-changes">
                <?php if (empty($changes)): ?>
                    <span style="color: #888;">No changes</span>
                <?php else: ?>
                    <?php foreach ($changes as $target => $change):
                        $delta = $change['delta'] ?? 0;
                        $deltaClass = $delta > 0 ? 'rel-delta-pos' : ($delta < 0 ? 'rel-delta-neg' : 'rel-delta-zero');
                        $deltaSign = $delta > 0 ? '+' : '';
                        $reason = $change['reason'] ?? '';

                        // Check for type change
                        $typeChanged = !empty($change['type_changed']);
                        $oldType = $change['old_type'] ?? '';
                        $newType = $change['type'] ?? '';
                    ?>
                        <div class="rel-change-item">
                            <strong><?php echo htmlspecialchars($target); ?></strong>:
                            <span class="rel-delta <?php echo $deltaClass; ?>"><?php echo $deltaSign . $delta; ?></span>
                            <?php if ($typeChanged && $oldType && $newType): ?>
                                <span style="color: #f59e0b; font-weight: bold; margin-left: 8px;">
                                    📋 <?php echo htmlspecialchars($oldType); ?> → <?php echo htmlspecialchars($newType); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($reason): ?>
                                <div class="rel-reason"><?php echo htmlspecialchars($reason); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($contextText): ?>
                    <div class="rel-context-wrapper">
                        <span class="rel-context-toggle" onclick="toggleContext(<?php echo $rowId; ?>)">📋 Show Context</span>
                        <div id="ctx-<?php echo $rowId; ?>" class="rel-context-content"><?php echo htmlspecialchars($contextText); ?></div>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="pagination-buttons">
    <?php if ($page > 1): ?>
        <button class="btn-base btn-primary" onclick="goToPage(<?php echo $page - 1; ?>)">← Previous</button>
    <?php endif; ?>
    <span style="padding: 6px;">Page <?php echo $page; ?></span>
    <?php if (count($results) >= $limit): ?>
        <button class="btn-base btn-primary" onclick="goToPage(<?php echo $page + 1; ?>)">Next →</button>
    <?php endif; ?>
</div>

<?php endif; ?>

</main>

<script>
function confirmDelete(type) {
    if (type === 'all') {
        return confirm('⚠️ Delete ALL relationship logs?\n\nThis cannot be undone. The logs are only for visibility - relationship changes are already saved to NPCs.');
    } else {
        return confirm('Delete old relationship logs?\n\nThis cannot be undone.');
    }
}

function toggleContext(rowId) {
    const el = document.getElementById('ctx-' + rowId);
    const toggle = el.previousElementSibling;
    if (el.classList.contains('show')) {
        el.classList.remove('show');
        toggle.textContent = '📋 Show Context';
    } else {
        el.classList.add('show');
        toggle.textContent = '📋 Hide Context';
    }
}

function filterByType(type) {
    const url = new URL(window.location);
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
