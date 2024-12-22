console.log("[popup.js] Global scope: Script loaded.");

document.addEventListener('DOMContentLoaded', () => {
  console.log("[popup.js] DOMContentLoaded event fired.");

  const urlInput = document.getElementById('urlInput');
  const saveBtn = document.getElementById('saveBtn');
  const targetTabBtn = document.getElementById('targetTabBtn');
  const targetTabStatus = document.getElementById('targetTabStatus');
  const targetTabDropdown = document.getElementById('targetTabDropdown');
  const connectionStatus = document.getElementById('connectionStatus'); // Get the status element

  let targetedTabId = null;

  // Load saved URL and targeted tab ID
  chrome.storage.sync.get(['wsUrl', 'targetedTabId', 'wsConnected'], (data) => { // Load wsConnected
    console.log("[popup.js] Retrieved from storage:", data);
    if (data.wsUrl) {
      urlInput.value = data.wsUrl;
    } else {
      console.log("[popup.js] No wsUrl in storage. Using default placeholder.");
    }
    if (data.targetedTabId) {
      targetedTabId = data.targetedTabId;
      updateTargetTabStatus();
    }
    updateConnectionStatus(data.wsConnected); // Initial connection status update
  });

  saveBtn.addEventListener('click', () => {
    const url = urlInput.value.trim();
    console.log("[popup.js] Save button clicked. URL:", url);

    if (!url) {
      console.warn("[popup.js] No URL provided. Cannot save.");
      return;
    }

    // Save URL
    chrome.storage.sync.set({ wsUrl: url }, () => {
      console.log("[popup.js] URL saved.");
      alert('WebSocket URL saved!'); // Quick feedback for the user

      // Attempt to connect immediately after saving the URL
      chrome.runtime.sendMessage({ type: 'ATTEMPT_CONNECTION' });
    });
  });

  targetTabBtn.addEventListener('click', () => {
    console.log("[popup.js] Target Tab button clicked.");
    populateTargetTabDropdown();
  });

  function populateTargetTabDropdown() {
    chrome.tabs.query({ url: '*://chatgpt.com/*' }, (tabs) => {
      targetTabDropdown.innerHTML = ''; // Clear existing options
      if (tabs && tabs.length > 0) {
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select a ChatGPT Tab';
        targetTabDropdown.appendChild(defaultOption);

        tabs.forEach(tab => {
          const option = document.createElement('option');
          option.value = tab.id;
          option.textContent = `${tab.title} (ID: ${tab.id})`;
          option.selected = tab.id === targetedTabId;
          targetTabDropdown.appendChild(option);
        });
      } else {
        const noTabsOption = document.createElement('option');
        noTabsOption.value = '';
        noTabsOption.textContent = 'No ChatGPT tabs found';
        targetTabDropdown.appendChild(noTabsOption);
      }
    });
  }

  targetTabDropdown.addEventListener('change', () => {
    targetedTabId = parseInt(targetTabDropdown.value);
    chrome.storage.sync.set({ targetedTabId: targetedTabId }, () => {
      console.log("[popup.js] Targeted tab ID saved:", targetedTabId);
      updateTargetTabStatus();
    });
  });

  function updateTargetTabStatus() {
    if (targetedTabId) {
      chrome.tabs.get(targetedTabId, (tab) => {
        if (chrome.runtime.lastError) {
          targetTabStatus.textContent = 'Targeted tab not found.';
          targetedTabId = null;
          chrome.storage.sync.remove('targetedTabId');
        } else {
          targetTabStatus.textContent = `Targeting: ${tab.title} (ID: ${targetedTabId})`;
        }
      });
    } else {
      targetTabStatus.textContent = 'No tab targeted';
    }
  }

  function updateConnectionStatus(isConnected) {
    connectionStatus.textContent = `Status: ${isConnected ? 'Connected' : 'Disconnected'}`;
    connectionStatus.style.color = isConnected ? 'green' : 'red';
  }

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    console.log("[popup.js] onMessage received:", msg);
    if (msg.type === 'CONNECTION_STATUS') {
      updateConnectionStatus(msg.connected);
      sendResponse({ received: true });
    }
  });
});