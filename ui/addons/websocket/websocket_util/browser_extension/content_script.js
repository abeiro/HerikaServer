console.log("[content_script.js] Loaded and running.");

let lastProcessedMessage = "";

// Listen for messages from background script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.type === 'INPUT_FROM_SERVER') {
        console.log("[content_script.js] INPUT_FROM_SERVER received:", request.text);
        handleServerInput(request.text);
        sendResponse({ status: "received" });
    }
    return true;
});

// Handle server input: insert text, dispatch input/enter
function handleServerInput(text) {
    console.log("[content_script.js] Handling server input:", text);

    const targetElement = findDynamicInputField();
    if (!targetElement) {
        console.error("[content_script.js] No target input field found. Cannot insert text.");
        return;
    }

    console.log("[content_script.js] Target input field found:", targetElement);

    setNativeValue(targetElement, text);

    // Dispatch input event to notify internal logic that input changed
    const inputEvent = new Event('input', { bubbles: true });
    targetElement.dispatchEvent(inputEvent);
    console.log("[content_script.js] Dispatched input event after setting text.");

    // Slight delay before pressing Enter to allow UI updates
    setTimeout(() => {
        const confirmedTarget = findDynamicInputField();
        if (confirmedTarget) {
            console.log("[content_script.js] Confirmed input field still available before Enter.");
            confirmedTarget.focus();

            // Simulate pressing Enter
            const enterEvent = new KeyboardEvent('keydown', {
                bubbles: true,
                cancelable: true,
                key: 'Enter',
                code: 'Enter',
                keyCode: 13
            });
            confirmedTarget.dispatchEvent(enterEvent);
            console.log("[content_script.js] Enter key event dispatched.");
        } else {
            console.error("[content_script.js] Input field not found after text insertion. Submission aborted.");
        }
    }, 100);
}

// Set the native value of the target element
function setNativeValue(element, value) {
    console.log("[content_script.js] Setting native value of element...");
    element.textContent = value; // Always use textContent to reliably set text
    console.log("[content_script.js] Value set successfully to:", value);
}

// Find the dynamic input field by using fallback selectors
function findDynamicInputField() {
    console.log("[content_script.js] Attempting to find dynamic input field...");

// fallback if no placeholder is present
    const selectors = [
        'p[data-placeholder*="Message"]',
        'div[contenteditable="true"]' 
    ];

    for (const selector of selectors) {
        const element = document.querySelector(selector);
        if (element) {
            console.log("[content_script.js] Found input field using selector:", selector);
            return element;
        }
    }

    console.error("[content_script.js] Could not find dynamic input field with the fallback selectors.");
    return null;
}

// Observe the DOM for new assistant responses
const observer = new MutationObserver(() => {
    let assistantMessages = document.querySelectorAll('div[data-message-author-role="assistant"] .markdown');
    if (assistantMessages.length > 0) {
        const latestMessageElement = assistantMessages[assistantMessages.length - 1];
        const latestMessage = latestMessageElement.innerText.trim();

        if (latestMessage && latestMessage !== lastProcessedMessage) {
            console.log("[content_script.js] New assistant response detected:", latestMessage);
            lastProcessedMessage = latestMessage;

            // Relay response back to background
            chrome.runtime.sendMessage({
                type: 'RESPONSE_FROM_CHATGPT',
                response: latestMessage
            }, (response) => {
                if (chrome.runtime.lastError) {
                    console.error("[content_script.js] Error sending response to background:", chrome.runtime.lastError);
                } else {
                    console.log("[content_script.js] Response relayed to background successfully.");
                }
            });
        }
    }
});

observer.observe(document.body, { childList: true, subtree: true });
console.log("[content_script.js] MutationObserver set up to detect assistant responses.");
