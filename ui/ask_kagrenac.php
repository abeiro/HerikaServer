<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

// Determine web root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Get MCP server host from conf_opts (WSL_IP or fallback to localhost)
$db = $GLOBALS["db"];
$mcpHost = 'localhost';
try {
    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = 'Network/WSL_IP' LIMIT 1");
    if (isset($row['value']) && !empty($row['value'])) {
        $mcpHost = $row['value'];
    }
} catch (Exception $e) {
    // Fallback to localhost if query fails.
}

$TITLE = "Ask Kagrenac";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
body { margin: 0; padding: 20px; background: #2a2a2a; }

.kagrenac-container { max-width: 1200px; margin: 0 auto; }
.kagrenac-header {
    margin-bottom: 20px;
    padding: 18px 20px;
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
}
.kagrenac-header h2 {
    margin: 0 0 8px 0;
    color: rgb(242, 124, 17);
    font-family: 'MagicCards', sans-serif;
}
.kagrenac-header p {
    margin: 0;
    color: #ccc;
    font-size: 0.92em;
}

.chat-container {
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    overflow: hidden;
}

.chat-messages {
    height: calc(100vh - 260px);
    min-height: 460px;
    overflow-y: auto;
    padding: 20px;
}

.message {
    margin-bottom: 15px;
    padding: 12px 16px;
    border-radius: 8px;
    max-width: 90%;
}
.message.user {
    background: rgba(58, 58, 120, 0.4);
    border: 1px solid rgba(100, 100, 160, 0.3);
    margin-left: auto;
}
.message.assistant {
    background: rgba(58, 58, 58, 0.6);
    border: 1px solid #4a4a4a;
}
.message-role {
    font-weight: bold;
    margin-bottom: 5px;
    color: rgb(242, 124, 17);
    font-size: 0.9em;
}
.message-content {
    color: #f8f9fa;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.chat-input-area {
    padding: 15px;
    border-top: 1px solid #3a3a3a;
    background: rgba(34, 34, 34, 0.95);
}
.chat-input-group { display: flex; gap: 10px; }
.chat-input {
    flex: 1;
    padding: 10px 15px;
    background: rgba(58, 58, 58, 0.8);
    border: 1px solid #4a4a4a;
    border-radius: 6px;
    color: #f8f9fa;
    font-family: inherit;
    resize: none;
    min-height: 44px;
    max-height: 170px;
}
.chat-send-btn {
    padding: 10px 25px;
    background: linear-gradient(180deg, rgba(242, 124, 17, 0.9), rgba(220, 100, 10, 1));
    border: 1px solid rgba(242, 124, 17, 0.5);
    color: white;
    cursor: pointer;
    border-radius: 6px;
    font-family: 'MagicCards', sans-serif;
    transition: all 0.3s ease;
    white-space: nowrap;
}
.chat-send-btn:hover:not(:disabled) {
    background: linear-gradient(180deg, rgba(255, 140, 30, 1), rgba(242, 124, 17, 1));
}
.chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.status {
    margin: 0 0 10px 0;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.9em;
}
.status.error {
    background: rgba(200, 50, 50, 0.2);
    border: 1px solid rgba(200, 50, 50, 0.4);
    color: #ff8888;
}
</style>

<main class="kagrenac-container">
    <div class="kagrenac-header">
        <h2>Ask Kagrenac</h2>
        <p>MCP-powered debugging assistant for logs, files, and CHIM server state.</p>
    </div>

    <div id="statusArea"></div>

    <div class="chat-container">
        <div class="chat-messages" id="chatMessages">
            <div class="message assistant">
                <div class="message-role">Kagrenac</div>
                <div class="message-content">Ask me to inspect logs, config files, prompts, and server behavior. I use MCP tools for debugging context.</div>
            </div>
        </div>
        <div class="chat-input-area">
            <div class="chat-input-group">
                <textarea id="chatInput" class="chat-input" placeholder="Ask a debugging question..." rows="1"></textarea>
                <button id="sendBtn" class="chat-send-btn" type="button">Send</button>
            </div>
        </div>
    </div>
</main>

<script>
const mcpServerUrl = 'http://<?php echo $mcpHost; ?>:3100';
let conversationHistory = [];

function showError(message) {
    const area = document.getElementById('statusArea');
    area.innerHTML = `<div class="status error">${message}</div>`;
}

function addMessage(role, content) {
    const messagesDiv = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + role;

    const roleDiv = document.createElement('div');
    roleDiv.className = 'message-role';
    roleDiv.textContent = role === 'user' ? 'You' : 'Kagrenac';

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.textContent = content;

    messageDiv.appendChild(roleDiv);
    messageDiv.appendChild(contentDiv);
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const message = input.value.trim();
    if (!message) return;

    input.value = '';
    sendBtn.disabled = true;
    sendBtn.textContent = 'Thinking...';
    addMessage('user', message);

    try {
        const response = await fetch(mcpServerUrl + '/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, history: conversationHistory }),
        });

        const data = await response.json();
        if (data.error) {
            addMessage('assistant', 'Error: ' + data.error);
        } else {
            addMessage('assistant', data.response);
            conversationHistory.push(
                { role: 'user', content: message },
                { role: 'assistant', content: data.response }
            );
        }
    } catch (error) {
        showError('Unable to connect to MCP server at ' + mcpServerUrl + '.');
        addMessage('assistant', 'Error: MCP server unavailable. Check that CHIM-MCP is running.');
    } finally {
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send';
    }
}

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('chatInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$content = ob_get_contents();
ob_end_clean();
echo $content;
?>
