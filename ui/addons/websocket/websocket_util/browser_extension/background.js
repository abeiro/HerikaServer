console.log("[background.js] Service worker loaded.");

let ws = null;
let wsUrl = null;
let connected = false;
let pingInterval = null;
let targetedTabId = null;

chrome.storage.onChanged.addListener((changes, namespace) => {
  for (let [key, { oldValue, newValue }] of Object.entries(changes)) {
    if (key === 'targetedTabId') {
      targetedTabId = newValue;
      console.log("[background.js] Targeted tab ID updated:", targetedTabId);
    } else if (key === 'wsUrl') {
      wsUrl = newValue;
      console.log("[background.js] WebSocket URL updated:", wsUrl);
      // Attempt to connect when the URL is updated
      connectWebSocket();
    }
  }
});

// Retrieve targetedTabId and wsUrl on startup
chrome.storage.sync.get(['targetedTabId', 'wsUrl'], (data) => {
  if (data.targetedTabId) {
    targetedTabId = data.targetedTabId;
    console.log("[background.js] Initial targeted tab ID:", targetedTabId);
  }
  if (data.wsUrl) {
    wsUrl = data.wsUrl;
    console.log("[background.js] Initial WebSocket URL:", wsUrl);
    connectWebSocket(); // Attempt connection on startup
  }
});

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  console.log("[background.js] onMessage received:", msg);

  if (msg.type === 'ATTEMPT_CONNECTION') {
    console.log("[background.js] Received ATTEMPT_CONNECTION request.");
    connectWebSocket();
    sendResponse({ status: 'connection attempt initiated' });
    return true; // Keep the sendResponse alive for async
  }

  if (msg.type === 'RESPONSE_FROM_CHATGPT') {
    console.log("[background.js] RESPONSE_FROM_CHATGPT received:", msg.response);
    if (connected && ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({
        type: 'response',
        data: msg.response,
        msg_id: msg.msg_id  // pass along the msg_id
        }));
      console.log("[background.js] Sent response back to WebSocket server:", msg.response);
    } else {
      console.warn("[background.js] WebSocket not connected or not open. Cannot send response back.");
    }
  }
});

function connectWebSocket() {
  if (ws) {
    console.warn("[background.js] WebSocket already exists. Not reconnecting.");
    return;
  }
  if (!wsUrl) {
    console.warn("[background.js] WebSocket URL is not set. Cannot connect.");
    return;
  }
  console.log("[background.js] Attempting to connect to:", wsUrl);
  updateStatus(false); // Set status to disconnected before attempting to connect
  ws = new WebSocket(wsUrl);

  ws.onopen = () => {
    console.log("[background.js] WebSocket onopen fired. Connected!");
    connected = true;
    updateStatus(true);
    startPing();
  };

  ws.onmessage = (event) => {
    console.log("[background.js] WebSocket onmessage:", event.data);
    let data = null;
    try {
      data = JSON.parse(event.data);
    } catch (e) {
      console.error("[background.js] JSON parse error:", e);
    }
    if (!data) return;

    if (data.type === 'input') {
      console.log("[background.js] Received input from server:", data.text);
      if (targetedTabId !== null) {
        chrome.tabs.sendMessage(targetedTabId, {
          type: 'INPUT_FROM_SERVER',
          text: data.text,
          msg_id: data.msg_id
          }, (response) => {
          if (chrome.runtime.lastError) {
            console.error("[background.js] Error sending message to content script:", chrome.runtime.lastError);
          } else {
            console.log("[background.js] Message sent to content script:", response);
          }
        });
      } else {
        console.warn("[background.js] No target ChatGPT tab selected. Cannot send input to content script.");
      }
    }
  };

  ws.onclose = () => {
    console.warn("[background.js] WebSocket closed.");
    cleanupConnection();
    updateStatus(false);
  };

  ws.onerror = (err) => {
    console.error("[background.js] WebSocket error:", err);
    cleanupConnection();
    updateStatus(false);
  };
}

function disconnectWebSocket() {
  console.log("[background.js] disconnectWebSocket called.");
  if (ws && ws.readyState === WebSocket.OPEN) {
    ws.close();
  } else {
    cleanupConnection();
  }
  updateStatus(false);
}

function cleanupConnection() {
  console.log("[background.js] cleanupConnection: Cleaning up.");
  stopPing();
  if (ws) {
    ws = null;
  }
  connected = false;
}

function updateStatus(isConnected) {
  console.log("[background.js] updateStatus:", isConnected);
  connected = isConnected; // Update the module-level connected variable
  chrome.storage.sync.set({ wsConnected: isConnected }, () => {
    chrome.runtime.sendMessage({ type: 'CONNECTION_STATUS', connected: isConnected });
  });
}

function startPing() {
  stopPing();
  console.log("[background.js] startPing: Starting ping interval.");
  pingInterval = setInterval(() => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      console.log("[background.js] Sending ping.");
      ws.send(JSON.stringify({ type: 'ping' }));
    } else {
      console.warn("[background.js] Cannot ping. WS not open.");
    }
  }, 30000); // 30 second keep-alive
}

function stopPing() {
  if (pingInterval) {
    console.log("[background.js] stopPing: Clearing ping interval.");
    clearInterval(pingInterval);
  }
  pingInterval = null;
}

// Periodic alarm to keep worker alive and check/reconnect
chrome.alarms.create("keepAlive", { periodInMinutes: 1 });
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "keepAlive") {
    console.log("[background.js] Keeping the service worker alive.");
    // Attempt to connect if not already connected and the URL is present
    if (!connected && wsUrl) {
      connectWebSocket();
    }
  }
});