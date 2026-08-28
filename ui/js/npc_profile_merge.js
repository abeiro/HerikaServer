// Reversible profile sharing for NPC rows that are one character.
//
// This dialog only ever creates manual pairs, and every step is explicit: the operator picks one
// other same-name profile, compares the fields that will be replaced, chooses which profile is
// kept, and confirms the two actors are the same character. A group may instead have been linked
// automatically by the server; the shared panel says so and Unlink reverses either kind. All
// physical actor rows survive, and Unlink separates every actor in the group at once.
(function () {
    'use strict';

    const API = '../api/chim_npc_profile_merge.php';
    const NO_REFID_LABEL = 'No RefID';
    const UNKNOWN_ORIGIN_LABEL = 'Unknown plugin';

    const backdrop = document.getElementById('npc_merge_modal');
    if (!backdrop) return;

    const byId = (id) => document.getElementById(id);
    const container = backdrop.querySelector('.modal-container');
    const statusLine = byId('npc_merge_status');
    const errorLine = byId('npc_merge_error');
    const sharedPanel = byId('npc_merge_shared_panel');
    const selectPanel = byId('npc_merge_select_panel');
    const comparePanel = byId('npc_merge_compare_panel');
    const sharedList = byId('npc_merge_shared_list');
    const sharedKind = byId('npc_merge_shared_kind');
    const unlinkWarn = byId('npc_merge_unlink_warn');
    const unlinkConfirmLabel = byId('npc_merge_unlink_confirm_label');
    const autoNote = byId('npc_merge_auto_note');
    const candidateList = byId('npc_merge_candidates');
    const compareGrid = byId('npc_merge_compare');
    const unlinkConfirm = byId('npc_merge_unlink_confirm');
    const unlinkButton = byId('npc_merge_unlink');
    const compareButton = byId('npc_merge_compare_btn');
    const sameCharacter = byId('npc_merge_same_character');
    const submitButton = byId('npc_merge_submit');
    const backButton = byId('npc_merge_back');
    const closeButton = byId('npc_merge_close');

    // Fields the operator is asked to compare before choosing a keeper.
    const COMPARE_FIELDS = [
        { key: 'npc_static_bio', label: 'Biography', from: 'fields' },
        { key: 'personality', label: 'Personality', from: 'fields' },
        { key: 'voiceid', label: 'Voice', from: 'fields' },
        { key: 'goals', label: 'Goals', from: 'fields' },
        { key: 'profile_id', label: 'Profile', from: 'fields' },
        { key: 'memory', label: 'Memory', from: 'root' },
        { key: 'relationships', label: 'Relationships', from: 'root' }
    ];

    let state = null;
    let previousFocus = null;
    let previousBodyOverflow = '';
    let requestGeneration = 0;
    let busy = false;

    function normalizeRefid(value) {
        const raw = String(value == null ? '' : value).trim().replace(/^0x/i, '').toUpperCase();
        return /^[0-9A-F]{1,8}$/.test(raw) ? raw.padStart(8, '0') : '';
    }

    function refidText(value) {
        return normalizeRefid(value) || NO_REFID_LABEL;
    }

    // The reference origin is the plugin recorded in refid_source, not the first entry of
    // metadata.mods: a later plugin can override an actor without owning its reference.
    function originText(value) {
        const raw = String(value == null ? '' : value).trim();
        if (!raw) return '';
        // refid_source is "<plugin>|<local form id>"; the local id only repeats the RefID here,
        // so the origin reads as the plugin that owns the reference.
        const match = raw.match(/^([^/\\|@#:]+\.es[mpl])(?:[/|][0-9A-Fa-f]{1,8})?$/);
        return match ? match[1] : raw;
    }

    function profileLabel(value) {
        const id = String(value == null ? '' : value).trim();
        if (!id) return '';
        const map = window.PROFILES_BY_ID;
        const label = map && Object.prototype.hasOwnProperty.call(map, id) ? map[id] : '';
        return label || `Profile #${id}`;
    }

    function relationshipText(value) {
        if (!value || typeof value !== 'object') return '';
        const entries = Object.keys(value);
        if (!entries.length) return '';
        return entries.map((target) => {
            const entry = value[target] || {};
            const parts = [];
            if (entry.type) parts.push(String(entry.type));
            if (entry.aff !== undefined && entry.aff !== null && entry.aff !== '') parts.push(`affinity ${Number(entry.aff)}`);
            const note = String(entry.note || entry.relation || '').trim();
            if (note) parts.push(note);
            return parts.length ? `${target}: ${parts.join(', ')}` : String(target);
        }).join('\n');
    }

    function fieldText(profile, field) {
        const bag = field.from === 'fields' ? (profile && profile.fields) || {} : profile || {};
        const value = bag[field.key];
        if (field.key === 'relationships') return relationshipText(value);
        if (field.key === 'profile_id') return profileLabel(value);
        return String(value == null ? '' : value).trim();
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function srOnly(text) {
        return element('span', 'npc-sr-only', text);
    }

    // One shared shape for a candidate row, a shared member and the current actor.
    function identityMeta(entry) {
        const meta = element('span', 'npc-merge-option-meta');
        const refid = element('span', 'npc-merge-refid');
        refid.append(srOnly('Ref ID '), document.createTextNode(refidText(entry && entry.refid)));
        if (!normalizeRefid(entry && entry.refid)) refid.classList.add('npc-merge-unknown');
        meta.appendChild(refid);
        const origin = originText(entry && entry.refid_source);
        const originNode = element('span', 'npc-merge-origin');
        originNode.append(srOnly('Reference origin '), document.createTextNode(origin || UNKNOWN_ORIGIN_LABEL));
        if (!origin) originNode.classList.add('npc-merge-unknown');
        meta.appendChild(originNode);
        return meta;
    }

    function setStatus(message) {
        statusLine.textContent = message || '';
    }

    function setError(message) {
        errorLine.textContent = message || '';
        errorLine.hidden = !message;
    }

    function setBusy(next) {
        busy = !!next;
        [compareButton, submitButton, unlinkButton, backButton].forEach((button) => {
            if (button) button.disabled = busy || button.dataset.blocked === '1';
        });
    }

    function gate(button, allowed) {
        if (!button) return;
        button.dataset.blocked = allowed ? '0' : '1';
        button.disabled = busy || !allowed;
    }

    function showPanel(panel) {
        [sharedPanel, selectPanel, comparePanel].forEach((section) => {
            section.hidden = section !== panel;
        });
    }

    function focusable() {
        return Array.from(container.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )).filter((node) => !node.disabled && node.offsetParent !== null);
    }

    function isOpen() {
        return backdrop.style.display === 'flex';
    }

    function open(rowId, trigger) {
        previousFocus = trigger || document.activeElement;
        previousBodyOverflow = document.body.style.overflow;
        backdrop.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        state = { rowId: String(rowId), csrf: '', current: null, candidates: [], sharing: null, otherId: '', keepId: '', revision: '' };
        setError('');
        showPanel(selectPanel);
        candidateList.replaceChildren(element('li', 'npc-merge-empty', 'Loading same-name profiles...'));
        compareGrid.replaceChildren();
        unlinkConfirm.checked = false;
        unlinkConfirm.disabled = false;
        autoNote.textContent = '';
        autoNote.hidden = true;
        sharedKind.textContent = '';
        sharedKind.hidden = true;
        unlinkButton.textContent = 'Unlink shared profile';
        sameCharacter.checked = false;
        gate(compareButton, false);
        gate(submitButton, false);
        gate(unlinkButton, false);
        closeButton.focus();
        loadOverview();
    }

    function close() {
        requestGeneration += 1;
        backdrop.style.display = 'none';
        document.body.style.overflow = previousBodyOverflow;
        state = null;
        setBusy(false);
        setStatus('');
        setError('');
        const restore = previousFocus;
        previousFocus = null;
        // Search or pagination may have replaced the card that opened the dialog.
        if (restore && document.contains(restore)) {
            try { restore.focus(); } catch (_error) { /* focus is best effort */ }
        } else {
            const search = document.getElementById('npc_search');
            if (search) { try { search.focus(); } catch (_error) { /* ignore */ } }
        }
    }

    async function request(options) {
        const response = await fetch(options.url, options.init);
        let payload = null;
        try { payload = await response.json(); } catch (_error) { payload = null; }
        if (!payload || payload.status !== 'success') {
            const message = (payload && payload.message) || `Request failed (HTTP ${response.status})`;
            const error = new Error(message);
            error.httpStatus = response.status;
            throw error;
        }
        return payload;
    }

    async function loadOverview() {
        const generation = ++requestGeneration;
        setBusy(true);
        setStatus('Loading same-name profiles...');
        try {
            const payload = await request({
                url: `${API}?id=${encodeURIComponent(state.rowId)}`,
                init: { cache: 'no-store', credentials: 'same-origin' }
            });
            if (generation !== requestGeneration || !state) return;
            state.csrf = String(payload.csrf_token || '');
            state.current = payload.current || null;
            state.candidates = Array.isArray(payload.candidates) ? payload.candidates : [];
            state.sharing = payload.sharing || null;
            state.revision = String(
                (payload.sharing && payload.sharing.revision) || payload.revision || ''
            );
            setStatus('');
            renderOverview();
            if (document.activeElement === closeButton) {
                const first = container.querySelector(
                    '.npc-merge-panel:not([hidden]) input:not([disabled]), .npc-merge-panel:not([hidden]) button:not([disabled])'
                );
                if (first) first.focus();
            }
        } catch (error) {
            if (generation !== requestGeneration) return;
            candidateList.replaceChildren();
            setStatus('');
            setError(error.message || 'Could not load same-name profiles.');
        } finally {
            if (generation === requestGeneration) setBusy(false);
        }
    }

    function renderCurrent() {
        const current = state.current || {};
        byId('npc_merge_current_name').textContent = current.name || 'Unknown NPC';
        const refid = byId('npc_merge_current_refid');
        refid.replaceChildren(srOnly('Ref ID '), document.createTextNode(refidText(current.refid)));
        refid.classList.toggle('npc-merge-unknown', !normalizeRefid(current.refid));
        const origin = byId('npc_merge_current_origin');
        const originValue = originText(current.refid_source);
        origin.replaceChildren(srOnly('Reference origin '), document.createTextNode(originValue || UNKNOWN_ORIGIN_LABEL));
        origin.classList.toggle('npc-merge-unknown', !originValue);
    }

    // The automatic link for a group is switched off for good once it has been unlinked, so the
    // note has to survive the row dropping back to the manual picker.
    function renderAutoNote() {
        const disabled = !!(state.sharing && state.sharing.auto_link_disabled);
        autoNote.textContent = disabled
            ? 'Automatic linking is switched off for this character for the rest of this playthrough. Same-name actors can still be merged by hand.'
            : '';
        autoNote.hidden = !disabled;
    }

    function renderOverview() {
        renderCurrent();
        renderAutoNote();
        if (state.sharing && state.sharing.linked) {
            renderShared();
            showPanel(sharedPanel);
            return;
        }
        renderCandidates();
        showPanel(selectPanel);
    }

    // Members of an automatic group can carry different names, so the row is always labelled with
    // the name the server reported for it rather than the name of the actor that opened the dialog.
    function renderShared() {
        const sharing = state.sharing || {};
        const members = Array.isArray(sharing.members) ? sharing.members : [];
        const ownerId = String(sharing.owner_id || '');
        const automatic = !!sharing.automatic;
        const owner = members.find((member) => String(member.id) === ownerId);
        const ownerName = String((owner && owner.name) || '').trim();
        const count = members.length;

        sharedKind.textContent = automatic
            ? 'Linked automatically. These references are known to be one character and use the kept profile below.'
            : '';
        sharedKind.hidden = !automatic;

        const scope = count > 2
            ? 'Unlinking separates all ' + count + ' actors in this group.'
            : (count === 2 ? 'Unlinking separates both actors in this group.' : 'Unlinking separates every actor in this group.');
        const keeper = ownerName ? 'The kept profile (' + ownerName + ')' : 'The kept profile';
        const automaticTail = automatic
            ? ' Automatic linking then stays off for this character for the rest of this playthrough, unless these rows are deleted.'
            : '';
        unlinkWarn.textContent = scope + ' Every other actor gets its own original character data back. '
            + keeper + ' retains its current data, including memory written while shared. '
            + 'That shared-period memory cannot be split apart again.' + automaticTail;
        unlinkConfirmLabel.textContent = automatic
            ? 'I understand that memory written while shared stays with the kept profile, and that this group will not be linked automatically again.'
            : 'I understand that memory written while shared stays with the kept profile.';
        unlinkButton.textContent = count > 2 ? 'Unlink all ' + count + ' actors' : 'Unlink shared profile';

        sharedList.replaceChildren();
        if (!members.length) {
            sharedList.appendChild(element('li', 'npc-merge-empty', 'No actors are listed for this shared profile.'));
        }
        members.forEach((member) => {
            const item = element('li', 'npc-merge-option');
            const copy = element('div', 'npc-merge-option-copy');
            const name = element('span', 'npc-merge-option-name');
            name.textContent = member.name || 'Unknown NPC';
            if (String(member.id) === ownerId) {
                name.append(document.createTextNode(' '), element('span', 'npc-shared-badge', 'Kept profile'));
            }
            if (String(member.id) === String(state.rowId)) {
                name.append(document.createTextNode(' '), element('span', 'npc-merge-origin', '(this actor)'));
            }
            copy.append(name, identityMeta(member));
            item.appendChild(copy);
            sharedList.appendChild(item);
        });

        gate(unlinkButton, unlinkConfirm.checked);
    }

    // Mirrors the checks the merge API applies, so an actor it will refuse is disabled with the
    // reason instead of failing only after the operator asked to compare. Plugin availability is
    // still decided server-side.
    function ineligibleReason(entry, owners) {
        const refid = normalizeRefid(entry && entry.refid);
        if (!refid) return 'No RefID recorded, so this actor cannot be identified.';
        if (refid.startsWith('FF')) return 'Runtime RefID. Only plugin-defined references can be merged.';
        if (!String((entry && entry.refid_source) || '').trim()) return 'No reference origin recorded for this actor.';
        if (Number((entry && entry.profile_owner_npc_id) || 0) > 0) return 'Already shares another profile. Unlink it first.';
        if (owners.has(Number(entry && entry.id))) return 'Already the kept profile of another actor. Unlink it first.';
        return '';
    }

    // Owners are not flagged on their own row, so derive them from the same-name set.
    function ownerIds() {
        const owners = new Set();
        [state.current].concat(state.candidates).forEach((entry) => {
            const owner = Number((entry && entry.profile_owner_npc_id) || 0);
            if (owner > 0) owners.add(owner);
        });
        return owners;
    }

    function renderCandidates() {
        candidateList.replaceChildren();
        const owners = ownerIds();
        const currentBlocked = ineligibleReason(state.current, owners);
        if (currentBlocked) {
            candidateList.appendChild(element('li', 'npc-merge-empty', `This actor cannot be merged: ${currentBlocked}`));
            state.otherId = '';
            gate(compareButton, false);
            return;
        }
        if (!state.candidates.length) {
            candidateList.appendChild(element('li', 'npc-merge-empty', 'No other profile carries this name.'));
            gate(compareButton, false);
            return;
        }
        let selectable = 0;
        state.candidates.forEach((candidate) => {
            const item = document.createElement('li');
            const label = element('label', 'npc-merge-option');
            const blocked = ineligibleReason(candidate, owners);
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'npc_merge_candidate';
            input.value = String(candidate.id);
            input.disabled = !!blocked;
            input.checked = !blocked && String(candidate.id) === String(state.otherId);
            input.addEventListener('change', () => {
                state.otherId = String(candidate.id);
                gate(compareButton, true);
            });
            const copy = element('div', 'npc-merge-option-copy');
            const name = element('span', 'npc-merge-option-name', candidate.name || 'Unknown NPC');
            copy.append(name, identityMeta(candidate));
            if (blocked) {
                label.classList.add('is-blocked');
                copy.appendChild(element('span', 'npc-merge-blocked', blocked));
            } else {
                selectable += 1;
            }
            label.append(input, copy);
            item.appendChild(label);
            candidateList.appendChild(item);
        });
        if (!selectable) {
            candidateList.appendChild(element(
                'li',
                'npc-merge-empty',
                'None of these actors can be merged right now. Each needs a plugin-defined RefID and no existing share.'
            ));
        }
        const stillSelected = candidateList.querySelector('input[name="npc_merge_candidate"]:checked');
        if (!stillSelected) state.otherId = '';
        gate(compareButton, !!state.otherId);
    }

    async function loadPreview() {
        if (!state || !state.otherId) return;
        const generation = ++requestGeneration;
        setBusy(true);
        setStatus('Comparing profiles...');
        setError('');
        try {
            const payload = await request({
                url: API,
                init: {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: state.csrf,
                        action: 'preview',
                        ids: [Number(state.rowId), Number(state.otherId)]
                    })
                }
            });
            if (generation !== requestGeneration || !state) return;
            state.revision = String(payload.revision || '');
            state.profiles = Array.isArray(payload.profiles) ? payload.profiles : [];
            state.keepId = '';
            sameCharacter.checked = false;
            gate(submitButton, false);
            setStatus('');
            renderCompare();
            showPanel(comparePanel);
            const firstKeeper = comparePanel.querySelector('input[name="npc_merge_keeper"]');
            if (firstKeeper) firstKeeper.focus();
        } catch (error) {
            if (generation !== requestGeneration) return;
            setStatus('');
            setError(error.message || 'Could not compare these profiles.');
        } finally {
            if (generation === requestGeneration) setBusy(false);
        }
    }

    function renderCompare() {
        compareGrid.replaceChildren();
        const profiles = state.profiles || [];
        if (profiles.length < 2) {
            compareGrid.appendChild(element('p', 'npc-merge-empty', 'The server did not return both profiles to compare.'));
            return;
        }
        profiles.forEach((profile) => {
            const column = element('div', 'npc-merge-column');
            column.dataset.id = String(profile.id);
            const keeper = element('label', 'npc-merge-keeper');
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'npc_merge_keeper';
            input.value = String(profile.id);
            input.setAttribute('aria-label', `Keep ${profile.name || 'NPC'} profile, RefID ${refidText(profile.refid)}`);
            input.addEventListener('change', () => {
                state.keepId = String(profile.id);
                compareGrid.querySelectorAll('.npc-merge-column').forEach((node) => {
                    node.classList.toggle('is-keeper', node.dataset.id === state.keepId);
                });
                updateSubmitGate();
            });
            keeper.append(input, document.createTextNode('Keep this profile'));
            column.appendChild(keeper);

            const heading = element('div', 'npc-merge-option-name', profile.name || 'Unknown NPC');
            column.append(heading, identityMeta(profile));

            const fields = element('dl', 'npc-merge-fields');
            COMPARE_FIELDS.forEach((field) => {
                const wrap = element('div', 'npc-merge-field');
                wrap.appendChild(element('dt', null, field.label));
                const value = fieldText(profile, field);
                const dd = element('dd', null, value || 'Not set');
                if (!value) dd.classList.add('is-empty');
                wrap.appendChild(dd);
                fields.appendChild(wrap);
            });
            column.appendChild(fields);
            compareGrid.appendChild(column);
        });
    }

    function updateSubmitGate() {
        gate(submitButton, !!state && !!state.keepId && sameCharacter.checked);
    }

    async function runAction(body, pendingMessage, doneMessage) {
        const generation = ++requestGeneration;
        setBusy(true);
        setStatus(pendingMessage);
        setError('');
        try {
            await request({
                url: API,
                init: {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                }
            });
            if (generation !== requestGeneration) return;
            close();
            refreshList(doneMessage);
        } catch (error) {
            if (generation !== requestGeneration) return;
            setStatus('');
            const stale = error.httpStatus === 409;
            setError(stale
                ? `${error.message || 'These profiles changed while the dialog was open.'} Reloading the current data.`
                : (error.message || 'The action could not be completed.'));
            setBusy(false);
            if (stale) loadOverview();
        }
    }

    function mergeProfiles() {
        if (!state || !state.keepId || !sameCharacter.checked) return;
        const otherId = [state.rowId, state.otherId].find((id) => String(id) !== String(state.keepId));
        runAction({
            csrf_token: state.csrf,
            action: 'merge',
            owner_id: Number(state.keepId),
            other_id: Number(otherId),
            revision: state.revision
        }, 'Merging profiles...', 'Profiles merged. Both actor cards now show Shared profile.');
    }

    function unlinkProfile() {
        if (!state || !unlinkConfirm.checked) return;
        const members = Array.isArray(state.sharing && state.sharing.members) ? state.sharing.members.length : 0;
        runAction({
            csrf_token: state.csrf,
            action: 'unlink',
            id: Number(state.rowId),
            revision: state.revision
        }, 'Unlinking...', members > 2
            ? 'Profiles unlinked. All ' + members + ' actors use their own profile again.'
            : 'Profiles unlinked. Each actor uses its own profile again.');
    }

    // No polling: the list is refreshed once, after a successful merge or unlink.
    function refreshList(message) {
        try {
            const toast = document.getElementById('toast');
            if (toast && message) {
                toast.querySelector('.message').textContent = message;
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2500);
            }
        } catch (_error) { /* the toast is optional */ }
        if (typeof window.NPC_REFRESH_LIST === 'function') {
            window.NPC_REFRESH_LIST();
            return;
        }
        window.location.reload();
    }

    // Delegated so the action keeps working after search or pagination replaces every card.
    document.addEventListener('click', function (event) {
        const trigger = event.target && event.target.closest ? event.target.closest('[data-merge-id]') : null;
        if (!trigger) return;
        event.preventDefault();
        event.stopPropagation();
        open(trigger.getAttribute('data-merge-id'), trigger);
    });

    closeButton.addEventListener('click', close);
    backButton.addEventListener('click', function () {
        setError('');
        setStatus('');
        renderCandidates();
        showPanel(selectPanel);
        const selected = candidateList.querySelector('input[name="npc_merge_candidate"]:checked')
            || candidateList.querySelector('input[name="npc_merge_candidate"]');
        if (selected) selected.focus();
    });
    compareButton.addEventListener('click', loadPreview);
    submitButton.addEventListener('click', mergeProfiles);
    unlinkButton.addEventListener('click', unlinkProfile);
    sameCharacter.addEventListener('change', updateSubmitGate);
    unlinkConfirm.addEventListener('change', function () {
        gate(unlinkButton, unlinkConfirm.checked);
    });

    // Capture phase: while this dialog owns the screen, Escape must not reach the card editor's
    // own Escape handler on this page or any host frame above it.
    document.addEventListener('keydown', function (event) {
        if (!isOpen()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const nodes = focusable();
        if (!nodes.length) return;
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        const active = document.activeElement;
        if (event.shiftKey && (active === first || !container.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !container.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    }, true);
}());
