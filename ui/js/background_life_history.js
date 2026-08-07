(function () {
    'use strict';

    function initBackgroundLifeMap() {
        const viewport = document.getElementById('bglMapViewport');
        const canvas = document.getElementById('bglMapCanvas');
        const image = document.getElementById('mapImage');
        const zoomValue = document.getElementById('bglMapZoomValue');
        const zoomInButton = document.querySelector('[data-map-zoom-in]');
        const zoomOutButton = document.querySelector('[data-map-zoom-out]');
        const resetButton = document.querySelector('[data-map-reset]');
        if (!viewport || !canvas || !image || !zoomValue || !zoomInButton || !zoomOutButton || !resetButton) {
            return;
        }

        const storageKey = 'bglMapCameraV1';
        const provinceBounds = {
            left: 0.025,
            top: 0.14,
            right: 0.98,
            bottom: 0.89
        };
        const state = {
            fitScale: 1,
            zoom: 1,
            scale: 1,
            x: 0,
            y: 0,
            initialized: false,
            dragging: false,
            pointerId: null,
            lastPointerX: 0,
            lastPointerY: 0
        };
        let resizeTimer = null;

        function clamp(value, minimum, maximum) {
            return Math.min(maximum, Math.max(minimum, value));
        }

        function updateFitScale() {
            const provinceWidth = canvas.offsetWidth * (provinceBounds.right - provinceBounds.left);
            const provinceHeight = canvas.offsetHeight * (provinceBounds.bottom - provinceBounds.top);
            if (!provinceWidth || !provinceHeight) {
                return false;
            }

            state.fitScale = Math.min(
                viewport.clientWidth / provinceWidth,
                viewport.clientHeight / provinceHeight
            ) * 0.96;
            return Number.isFinite(state.fitScale) && state.fitScale > 0;
        }

        function clampPan() {
            const scaledWidth = canvas.offsetWidth * state.scale;
            const scaledHeight = canvas.offsetHeight * state.scale;

            state.x = scaledWidth <= viewport.clientWidth
                ? (viewport.clientWidth - scaledWidth) / 2
                : clamp(state.x, viewport.clientWidth - scaledWidth, 0);
            state.y = scaledHeight <= viewport.clientHeight
                ? (viewport.clientHeight - scaledHeight) / 2
                : clamp(state.y, viewport.clientHeight - scaledHeight, 0);
        }

        function renderCamera() {
            clampPan();
            canvas.style.transform = 'translate3d(' + state.x + 'px, ' + state.y + 'px, 0) scale(' + state.scale + ')';
            canvas.style.setProperty('--bgl-info-scale', String(1 / state.zoom));
            zoomValue.textContent = Math.round(state.zoom * 100) + '%';
        }

        function currentCenter() {
            return {
                x: (viewport.clientWidth / 2 - state.x) / (canvas.offsetWidth * state.scale),
                y: (viewport.clientHeight / 2 - state.y) / (canvas.offsetHeight * state.scale)
            };
        }

        function saveCamera() {
            if (!state.initialized) {
                return;
            }

            const center = currentCenter();
            localStorage.setItem(storageKey, JSON.stringify({
                version: 1,
                zoom: state.zoom,
                centerX: center.x,
                centerY: center.y
            }));
        }

        function setCameraCenter(centerX, centerY) {
            state.scale = state.fitScale * state.zoom;
            state.x = viewport.clientWidth / 2 - centerX * canvas.offsetWidth * state.scale;
            state.y = viewport.clientHeight / 2 - centerY * canvas.offsetHeight * state.scale;
            renderCamera();
        }

        function fitProvince(savePreference) {
            if (!updateFitScale()) {
                return;
            }

            state.zoom = 1;
            setCameraCenter(
                (provinceBounds.left + provinceBounds.right) / 2,
                (provinceBounds.top + provinceBounds.bottom) / 2
            );
            state.initialized = true;
            if (savePreference) {
                saveCamera();
            }
        }

        function restoreCamera() {
            if (!updateFitScale()) {
                return false;
            }

            try {
                const saved = JSON.parse(localStorage.getItem(storageKey));
                if (
                    saved
                    && saved.version === 1
                    && Number.isFinite(saved.zoom)
                    && Number.isFinite(saved.centerX)
                    && Number.isFinite(saved.centerY)
                ) {
                    state.zoom = clamp(saved.zoom, 0.72, 3);
                    setCameraCenter(
                        clamp(saved.centerX, 0, 1),
                        clamp(saved.centerY, 0, 1)
                    );
                    state.initialized = true;
                    return true;
                }
            } catch (error) {
                localStorage.removeItem(storageKey);
            }

            return false;
        }

        function initializeCamera() {
            if (!canvas.offsetWidth || !canvas.offsetHeight) {
                return;
            }

            if (!restoreCamera()) {
                fitProvince(false);
            }
        }

        function zoomAt(factor, clientX, clientY) {
            if (!state.initialized) {
                initializeCamera();
            }

            const viewportRect = viewport.getBoundingClientRect();
            const focalX = clientX === undefined ? viewport.clientWidth / 2 : clientX - viewportRect.left;
            const focalY = clientY === undefined ? viewport.clientHeight / 2 : clientY - viewportRect.top;
            const mapX = (focalX - state.x) / state.scale;
            const mapY = (focalY - state.y) / state.scale;

            state.zoom = clamp(state.zoom * factor, 0.72, 3);
            state.scale = state.fitScale * state.zoom;
            state.x = focalX - mapX * state.scale;
            state.y = focalY - mapY * state.scale;
            renderCamera();
            saveCamera();
        }

        zoomInButton.addEventListener('click', function () {
            zoomAt(1.25);
        });
        zoomOutButton.addEventListener('click', function () {
            zoomAt(0.8);
        });
        resetButton.addEventListener('click', function () {
            fitProvince(true);
        });

        viewport.addEventListener('wheel', function (event) {
            event.preventDefault();
            zoomAt(event.deltaY < 0 ? 1.12 : 1 / 1.12, event.clientX, event.clientY);
        }, { passive: false });

        viewport.addEventListener('pointerdown', function (event) {
            if (
                event.button !== 0
                || event.target.closest('.marker, .location-marker, .map-navigation-controls')
            ) {
                return;
            }

            state.dragging = true;
            state.pointerId = event.pointerId;
            state.lastPointerX = event.clientX;
            state.lastPointerY = event.clientY;
            viewport.classList.add('is-dragging');
            viewport.setPointerCapture(event.pointerId);
        });

        viewport.addEventListener('pointermove', function (event) {
            if (!state.dragging || event.pointerId !== state.pointerId) {
                return;
            }

            state.x += event.clientX - state.lastPointerX;
            state.y += event.clientY - state.lastPointerY;
            state.lastPointerX = event.clientX;
            state.lastPointerY = event.clientY;
            renderCamera();
        });

        function finishDragging(event) {
            if (!state.dragging || event.pointerId !== state.pointerId) {
                return;
            }

            state.dragging = false;
            state.pointerId = null;
            viewport.classList.remove('is-dragging');
            if (viewport.hasPointerCapture(event.pointerId)) {
                viewport.releasePointerCapture(event.pointerId);
            }
            saveCamera();
        }

        viewport.addEventListener('pointerup', finishDragging);
        viewport.addEventListener('pointercancel', finishDragging);

        viewport.addEventListener('keydown', function (event) {
            if (event.target !== viewport) {
                return;
            }

            const panStep = 50;
            if (event.key === '+' || event.key === '=') {
                event.preventDefault();
                zoomAt(1.25);
            } else if (event.key === '-') {
                event.preventDefault();
                zoomAt(0.8);
            } else if (event.key === '0' || event.key === 'Home') {
                event.preventDefault();
                fitProvince(true);
            } else if (event.key.startsWith('Arrow')) {
                event.preventDefault();
                state.x += event.key === 'ArrowLeft' ? panStep : event.key === 'ArrowRight' ? -panStep : 0;
                state.y += event.key === 'ArrowUp' ? panStep : event.key === 'ArrowDown' ? -panStep : 0;
                renderCamera();
                saveCamera();
            }
        });

        window.focusBglMapMarker = function (marker) {
            const markerPosition = marker ? marker.closest('.marker') : null;
            if (!markerPosition) {
                return;
            }
            if (!state.initialized) {
                initializeCamera();
            }

            state.zoom = Math.max(state.zoom, 1.45);
            setCameraCenter(
                markerPosition.offsetLeft / canvas.offsetWidth,
                markerPosition.offsetTop / canvas.offsetHeight
            );
            saveCamera();
            viewport.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        if (image.complete) {
            requestAnimationFrame(initializeCamera);
        } else {
            image.addEventListener('load', initializeCamera, { once: true });
        }

        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                if (!state.initialized) {
                    initializeCamera();
                    return;
                }

                const center = currentCenter();
                updateFitScale();
                setCameraCenter(center.x, center.y);
            }, 120);
        });
    }

    initBackgroundLifeMap();

    const panel = document.getElementById('bgl-history-panel');
    const apiUrl = panel ? panel.dataset.apiUrl : '';
    const tableBody = document.getElementById('bgl-history-body');
    const status = document.getElementById('bgl-history-status');
    const npcFilter = document.getElementById('bgl-history-npc-filter');
    const searchInput = document.getElementById('bgl-history-search');
    const limitSelect = document.getElementById('bgl-history-limit');
    const refreshButton = document.getElementById('bgl-history-refresh');
    const liveButton = document.getElementById('bgl-history-live');
    const previousButton = document.getElementById('bgl-history-previous');
    const nextButton = document.getElementById('bgl-history-next');
    const pageLabel = document.getElementById('bgl-history-page-label');

    let currentPage = 1;
    let totalPages = 1;
    let liveTimer = null;
    let searchTimer = null;
    let requestController = null;
    let npcRecentEventsController = null;

    function createElement(tagName, className, text) {
        const element = document.createElement(tagName);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function nl2br(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).replace(/\r\n|\r|\n/g, '<br>');
    }

    function setLoading(loading) {
        panel.classList.toggle('bgl-history-loading', loading);
        refreshButton.disabled = loading;
    }

    function setStatus(message, isError) {
        status.textContent = message;
        status.style.color = isError ? '#e88989' : '';
    }

    function renderNpcOptions(npcs) {
        const selected = npcFilter.value;
        npcFilter.replaceChildren();

        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'All NPCs';
        npcFilter.appendChild(allOption);

        (npcs || []).forEach(function (npc) {
            const option = document.createElement('option');
            option.value = npc.name;
            option.textContent = npc.name + ' (' + npc.count + ')';
            npcFilter.appendChild(option);
        });

        npcFilter.value = selected;
    }

    function renderEmpty(message) {
        tableBody.replaceChildren();
        const row = document.createElement('tr');
        const cell = createElement('td', 'bgl-history-empty', message);
        cell.colSpan = 3;
        row.appendChild(cell);
        tableBody.appendChild(row);
    }

    function renderEntries(entries) {
        tableBody.replaceChildren();
        if (!entries || entries.length === 0) {
            renderEmpty('No Background Life activity matches these filters.');
            return;
        }

        entries.forEach(function (entry) {
            const row = createElement('tr', 'bgl-history-row');
            row.tabIndex = 0;
            row.setAttribute('aria-expanded', 'false');

            row.appendChild(createElement('td', 'bgl-history-time', entry.tamrielic_time || 'Unknown time'));

            const npcCell = document.createElement('td');
            npcCell.appendChild(createElement('span', 'bgl-history-npc', entry.npc || 'Unknown NPC'));
            row.appendChild(npcCell);

            const activityCell = document.createElement('td');
            const activity = createElement('div', 'bgl-history-activity');
            activity.appendChild(createElement('span', 'bgl-history-category', entry.category || 'activity'));
            activity.appendChild(createElement('span', 'bgl-history-activity-text', entry.activity || 'No details recorded'));
            activityCell.appendChild(activity);
            row.appendChild(activityCell);

            const detailsRow = createElement('tr', 'bgl-history-details');
            detailsRow.hidden = true;
            const detailsCell = document.createElement('td');
            detailsCell.colSpan = 3;
            
            if (entry.category == 'dialogue') {
                const detailsContent = createElement('div', 'bgl-history-details-content-text');
                detailsContent.innerHTML = nl2br(entry.activity) || 'No details recorded';
                const detailsMeta = createElement(
                    'div',
                    'bgl-history-details-meta',
                    [entry.server_time, entry.rowid ? 'History ID ' + entry.rowid : ''].filter(Boolean).join(' | ')
                );
                detailsContent.appendChild(detailsMeta);
                detailsCell.appendChild(detailsContent);
                detailsRow.appendChild(detailsCell);

            } else {
                const detailsContent = createElement(
                    'div',
                    'bgl-history-details-content',
                    entry.activity || 'No details recorded'
                );
                const detailsMeta = createElement(
                    'div',
                    'bgl-history-details-meta',
                    [entry.server_time, entry.rowid ? 'History ID ' + entry.rowid : ''].filter(Boolean).join(' | ')
                );
                detailsContent.appendChild(detailsMeta);
                detailsCell.appendChild(detailsContent);
                detailsRow.appendChild(detailsCell);
            }

            function toggleDetails() {
                const expanded = detailsRow.hidden;
                detailsRow.hidden = !expanded;
                row.classList.toggle('expanded', expanded);
                row.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

            row.addEventListener('click', toggleDetails);
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggleDetails();
                }
            });

            tableBody.appendChild(row);
            tableBody.appendChild(detailsRow);
        });
    }

    function renderPagination(pagination) {
        currentPage = pagination.current_page || 1;
        totalPages = pagination.total_pages || 1;
        previousButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;
        pageLabel.textContent = 'Page ' + currentPage + ' of ' + totalPages;
    }

    async function loadHistory() {
        if (requestController) {
            requestController.abort();
        }
        requestController = new AbortController();
        setLoading(true);
        setStatus('Loading activity...', false);

        const params = new URLSearchParams({
            page: String(currentPage),
            limit: limitSelect.value
        });
        if (npcFilter.value) {
            params.set('npc', npcFilter.value);
        }
        if (searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }

        try {
            const response = await fetch(apiUrl + '?' + params.toString(), {
                cache: 'no-store',
                credentials: 'same-origin',
                signal: requestController.signal
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.error || 'Unable to load activity');
            }

            renderNpcOptions(payload.npcs);
            renderEntries(payload.entries);
            renderPagination(payload.pagination || {});
            setStatus(
                (payload.pagination ? payload.pagination.total_records : payload.entries.length) +
                ' activit' +
                ((payload.pagination ? payload.pagination.total_records : payload.entries.length) === 1 ? 'y' : 'ies'),
                false
            );
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            renderEmpty('Background Life history could not be loaded.');
            setStatus(error.message || 'Unable to load activity', true);
        } finally {
            setLoading(false);
        }
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            currentPage = 1;
            loadHistory();
        }, 300);
    }

    function setLive(enabled) {
        if (liveTimer) {
            window.clearInterval(liveTimer);
            liveTimer = null;
        }

        liveButton.classList.toggle('active', enabled);
        liveButton.textContent = enabled ? 'Stop Live' : 'Auto Refresh';
        if (enabled) {
            liveTimer = window.setInterval(function () {
                if (!document.hidden) {
                    loadHistory();
                }
            }, 10000);
        }
    }

    function renderNpcRecentEvents(entries) {
        const list = document.getElementById('npc-recent-events-list');
        list.replaceChildren();

        if (!entries || entries.length === 0) {
            const empty = createElement(
                'div',
                'bgl-recent-events-empty',
                'No recent Background Life events have been recorded for this NPC.'
            );
            list.appendChild(empty);
            return;
        }

        entries.forEach(function (entry) {
            const eventItem = createElement('article', 'bgl-recent-event');
            const meta = createElement('div', 'bgl-recent-event-meta');
            meta.appendChild(createElement('span', '', entry.tamrielic_time || 'Unknown time'));
            meta.appendChild(createElement('span', 'bgl-recent-event-category', entry.category || 'activity'));
            eventItem.appendChild(meta);
            eventItem.appendChild(createElement(
                'div',
                'bgl-recent-event-text',
                entry.activity || 'No details recorded'
            ));
            list.appendChild(eventItem);
        });
    }

    function setNpcHistoryTab(tabName) {
        const panels = {
            events: document.getElementById('npc-event-history-panel'),
            letters: document.getElementById('npc-letters-history-panel'),
            thoughts: document.getElementById('npc-thoughts-history-panel')
        };
        if (!panels.events || !panels.letters || !panels.thoughts || !panels[tabName]) {
            return;
        }

        Object.entries(panels).forEach(function ([name, panel]) {
            panel.hidden = name !== tabName;
        });
        document.querySelectorAll('[data-npc-history-tab]').forEach(function (tab) {
            const selected = tab.dataset.npcHistoryTab === tabName;
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
    }

    function renderNpcWrittenHistory(npcName) {
        const lettersContent = document.getElementById('npc-letter-history-content');
        const thoughtsContent = document.getElementById('npc-thought-history-content');
        if (!lettersContent || !thoughtsContent) {
            return;
        }

        const data = window.npcDiaryData ? window.npcDiaryData[npcName] : null;
        if (data && typeof window.renderNpcDiaryContent === 'function') {
            window.renderNpcDiaryContent(data);
            return;
        }

        lettersContent.replaceChildren(createElement(
            'div',
            'bgl-recent-events-empty',
            'No letters have been recorded for this NPC.'
        ));
        thoughtsContent.replaceChildren(createElement(
            'div',
            'bgl-recent-events-empty',
            'No inner thoughts have been recorded for this NPC.'
        ));
    }

    async function openNpcRecentEvents(npcName) {
        const modal = document.getElementById('npc-recent-events');
        const title = document.getElementById('npc-recent-events-title');
        const status = document.getElementById('npc-recent-events-status');
        const list = document.getElementById('npc-recent-events-list');
        if (!modal || !title || !status || !list) {
            return;
        }

        title.textContent = npcName + ' History';
        status.textContent = 'Loading recent events...';
        status.style.color = '';
        list.replaceChildren();
        setNpcHistoryTab('events');
        renderNpcWrittenHistory(npcName);
        openBglModal('npc-recent-events');

        if (npcRecentEventsController) {
            npcRecentEventsController.abort();
        }
        npcRecentEventsController = new AbortController();

        const params = new URLSearchParams({
            npc: npcName,
            page: '1',
            limit: '20'
        });

        try {
            const response = await fetch(modal.dataset.apiUrl + '?' + params.toString(), {
                cache: 'no-store',
                credentials: 'same-origin',
                signal: npcRecentEventsController.signal
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.error || 'Unable to load recent events');
            }

            renderNpcRecentEvents(payload.entries);
            const totalRecords = payload.pagination ? payload.pagination.total_records : payload.entries.length;
            if (totalRecords > payload.entries.length) {
                status.textContent = 'Showing the latest ' + payload.entries.length + ' of ' + totalRecords + ' recorded events.';
            } else {
                status.textContent = totalRecords === 1
                    ? '1 recorded event'
                    : totalRecords + ' recorded events, newest first';
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            renderNpcRecentEvents([]);
            status.textContent = 'Recent events could not be loaded.';
            status.style.color = '#e88989';
        }
    }

    function closeNpcRecentEvents() {
        if (npcRecentEventsController) {
            npcRecentEventsController.abort();
            npcRecentEventsController = null;
        }
        closeBglModal('npc-recent-events');
    }

    document.querySelectorAll('.marker-item[data-npc-name]').forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('button, a, input, label, [data-map-focus], [data-npc-events]')) {
                return;
            }
            openNpcRecentEvents(card.dataset.npcName);
        });
    });

    document.querySelectorAll('.marker-dot[data-npc-name]').forEach(function (marker) {
        marker.addEventListener('click', function () {
            openNpcRecentEvents(marker.dataset.npcName);
        });
        marker.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openNpcRecentEvents(marker.dataset.npcName);
            }
        });
    });

    document.querySelectorAll('[data-npc-events]').forEach(function (control) {
        control.addEventListener('click', function (event) {
            event.stopPropagation();
            const card = control.closest('.marker-item[data-npc-name]');
            if (card) {
                openNpcRecentEvents(card.dataset.npcName);
            }
        });
        control.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                control.click();
            }
        });
    });

    document.addEventListener('click', function (event) {
        const tab = event.target.closest('[data-npc-history-tab]');
        if (tab) {
            setNpcHistoryTab(tab.dataset.npcHistoryTab);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeNpcRecentEvents();
        }
    });

    window.closeNpcRecentEvents = closeNpcRecentEvents;

    if (panel) {
        npcFilter.addEventListener('change', function () {
            currentPage = 1;
            loadHistory();
        });
        searchInput.addEventListener('input', scheduleSearch);
        limitSelect.addEventListener('change', function () {
            currentPage = 1;
            loadHistory();
        });
        refreshButton.addEventListener('click', loadHistory);
        liveButton.addEventListener('click', function () {
            setLive(!liveTimer);
        });
        previousButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage -= 1;
                loadHistory();
            }
        });
        nextButton.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage += 1;
                loadHistory();
            }
        });

        loadHistory();
    }
})();
