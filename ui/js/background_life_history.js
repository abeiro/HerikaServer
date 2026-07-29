(function () {
    'use strict';

    const panel = document.getElementById('bgl-history-panel');
    if (!panel) {
        return;
    }

    const apiUrl = panel.dataset.apiUrl;
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
})();
