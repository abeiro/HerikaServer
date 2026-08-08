(function () {
    'use strict';

    window.chimInitSettingsImport = function (options) {
        const importButton = document.getElementById(options.importButtonId);
        const fileInput = document.getElementById(options.fileInputId);
        if (!importButton || !fileInput) {
            return;
        }

        const scopeDetails = {
            player: { packageType: 'chim_player_settings', displayName: 'Player settings' },
            narration: { packageType: 'chim_narration_settings', displayName: 'Narration settings' },
            global: { packageType: 'chim_global_settings', displayName: 'Global Settings' }
        };
        const details = scopeDetails[options.scope];
        if (!details) {
            return;
        }
        const expectedType = details.packageType;
        const displayName = details.displayName;

        importButton.addEventListener('click', function () {
            fileInput.click();
        });

        fileInput.addEventListener('change', async function () {
            const file = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (!file) {
                return;
            }
            if (file.size > 1048576) {
                window.alert('Import files must be 1 MB or smaller.');
                return;
            }

            let exportData;
            try {
                exportData = JSON.parse(await file.text());
            } catch (_error) {
                window.alert('This file does not contain valid JSON.');
                return;
            }

            if (!exportData || exportData.package_type !== expectedType || !exportData.settings || typeof exportData.settings !== 'object') {
                window.alert('This file is not a valid ' + displayName + ' export.');
                return;
            }

            let settingCount = Object.keys(exportData.settings).length;
            if (options.scope === 'narration') {
                settingCount += exportData.prompts && typeof exportData.prompts === 'object'
                    ? Object.keys(exportData.prompts).length
                    : 0;
                settingCount += exportData.profile ? 1 : 0;
            }
            const exportVersion = exportData.export_version || 'unknown';
            const confirmed = window.confirm(
                'Import ' + settingCount + ' ' + displayName + ' fields from CHIM ' + exportVersion + '?\n\n' +
                'Only fields present in this file will be changed. Newer settings that are absent will keep their current values.'
            );
            if (!confirmed) {
                return;
            }

            importButton.disabled = true;
            try {
                const response = await fetch(options.endpoint + '?scope=' + encodeURIComponent(options.scope), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ export: exportData })
                });
                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.error || ('HTTP ' + response.status));
                }

                const result = payload.result || {};
                const applied = Array.isArray(result.applied) ? result.applied.length : 0;
                const unknown = Array.isArray(result.skipped_unknown) ? result.skipped_unknown.length : 0;
                const invalid = Array.isArray(result.skipped_invalid) ? result.skipped_invalid.length : 0;
                const warnings = Array.isArray(result.warnings) ? result.warnings : [];
                let message = displayName + ' imported successfully.\n\nApplied: ' + applied;
                if (unknown > 0) {
                    message += '\nUnsupported fields skipped: ' + unknown;
                }
                if (invalid > 0) {
                    message += '\nInvalid fields skipped: ' + invalid;
                }
                if (warnings.length > 0) {
                    message += '\n\n' + warnings.join('\n');
                }
                window.alert(message);
                window.location.reload();
            } catch (error) {
                window.alert('Import failed: ' + (error.message || 'Unknown error'));
            } finally {
                importButton.disabled = false;
            }
        });
    };
})();
