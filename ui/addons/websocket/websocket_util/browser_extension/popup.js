console.log("[popup.js] Global scope: Script loaded.");

document.addEventListener('DOMContentLoaded', () => {
  console.log("[popup.js] DOMContentLoaded event fired.");

  const urlInput = document.getElementById('urlInput');
  const connectBtn = document.getElementById('connectBtn');
  const statusText = document.getElementById('statusText');

  // Load saved URL and connection state
  chrome.storage.sync.get(['wsUrl', 'wsConnected'], (data) => {
    console.log("[popup.js] Retrieved from storage:", data);
    if (data.wsUrl) {
      urlInput.value = data.wsUrl;
    } else {
      console.log("[popup.js] No wsUrl in storage. Using default placeholder.");
    }
    updateUI(data.wsConnected);
  });

  connectBtn.addEventListener('click', () => {
    const url = urlInput.value.trim();
    console.log("[popup.js] Connect button clicked. URL:", url);

    if (!url) {
      console.warn("[popup.js] No URL provided. Cannot connect.");
      return;
    }

    // Save URL and request background to toggle connection
    chrome.storage.sync.set({ wsUrl: url }, () => {
      console.log("[popup.js] URL saved. Sending TOGGLE_CONNECTION to background.");
      chrome.runtime.sendMessage({ type: 'TOGGLE_CONNECTION' }, (response) => {
        console.log("[popup.js] Response from background on TOGGLE_CONNECTION:", response);
      });
    });
  });

  function updateUI(connected) {
    console.log("[popup.js] Updating UI. Connected:", connected);
    if (connected) {
      connectBtn.textContent = 'Disconnect';
      statusText.textContent = 'Connected';
    } else {
      connectBtn.textContent = 'Connect';
      statusText.textContent = 'Disconnected';
    }
  }

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    console.log("[popup.js] onMessage received:", msg);
    if (msg.type === 'CONNECTION_STATUS') {
      updateUI(msg.connected);
      sendResponse({ received: true });
    }
  });

  // Periodic UI refresh to handle unexpected disconnects
  setInterval(() => {
    chrome.storage.sync.get(['wsConnected'], (data) => {
      updateUI(data.wsConnected);
    });
  }, 10000); // Refresh every 10 seconds
});
