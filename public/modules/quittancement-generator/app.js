(function () {
    'use strict';

    const appData = window.__QG_APP__ || {};
    const defaultEmailData = appData.defaultEmailData || {};
    let dbTargets = normalizeDbTargets(appData.targets || []);
    let selectedTargetId = resolveSelectedTargetId(String(appData.selectedTargetId || ''));
    let isInitialized = false;

    function initializeApplication() {
        if (isInitialized) {
            return;
        }

        isInitialized = true;
        renderDbTargetSelect();
        renderDbTargetsList();
        syncTargetInput();
        updateExecutionTargetUi();
        initializeEventListeners();
        syncEmailCustomizationFlag();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApplication, { once: true });
    } else {
        initializeApplication();
    }

    function initializeEventListeners() {
        bindChange('year', updatePage);
        bindChange('month', updatePage);
        bindChange('qgTargetSelect', function () {
            syncSelectedTarget(getFieldValue('qgTargetSelect'));
            hideExecutionStatus();
        });

        ['emailTo', 'emailCc', 'emailSubject', 'emailBody'].forEach(function (id) {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', syncEmailCustomizationFlag);
            }
        });

        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                copyToClipboard(button.getAttribute('data-copy-target') || '', 'Bloc SQL copie.');
            });
        });

        document.querySelectorAll('[data-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                handleAction(button.getAttribute('data-action') || '');
            });
        });

        const dbTargetForm = document.getElementById('qgDbTargetForm');
        if (dbTargetForm) {
            dbTargetForm.addEventListener('submit', function (event) {
                event.preventDefault();
                submitDbTargetForm();
            });
        }

        const dbTargetsList = document.getElementById('qgDbTargetsList');
        if (dbTargetsList) {
            dbTargetsList.addEventListener('click', function (event) {
                const button = event.target.closest('[data-target-action]');
                if (!button) {
                    return;
                }

                const action = String(button.getAttribute('data-target-action') || '');
                const targetId = String(button.getAttribute('data-target-id') || '');
                if (action === 'use') {
                    syncSelectedTarget(targetId);
                    closeDbSettingsModal();
                } else if (action === 'delete') {
                    deleteDbTarget(targetId);
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDbSettingsModal();
            }
        });
    }

    function bindChange(id, handler) {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', handler);
        }
    }

    function handleAction(action) {
        switch (action) {
            case 'reset-dates':
                resetCustomDates();
                break;
            case 'copy-email':
                copyEmailToClipboard();
                break;
            case 'open-email':
                openEmailClient();
                break;
            case 'copy-all-sql':
                copyAllSQL();
                break;
            case 'download-sql':
                downloadSQL();
                break;
            case 'execute-sql':
                executeSqlBlocks();
                break;
            case 'open-db-settings':
                openDbSettingsModal();
                break;
            case 'close-db-settings':
                closeDbSettingsModal();
                break;
            case 'export-json':
                exportAsJSON();
                break;
            case 'export-csv':
                exportAsCSV();
                break;
            case 'print':
                window.print();
                break;
            default:
                break;
        }
    }

    function submitMonthForm() {
        const form = document.getElementById('monthForm');
        if (!form) {
            return;
        }

        syncEmailCustomizationFlag();
        syncTargetInput();

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    }

    function clearManualDateInputs() {
        ['d3_date', 'd15_date', 'd25_date', 'd27_date'].forEach(function (id) {
            const element = document.getElementById(id);
            if (element) {
                element.value = '';
            }
        });
    }

    function updatePage() {
        clearManualDateInputs();
        submitMonthForm();
    }

    function resetCustomDates() {
        clearManualDateInputs();
        submitMonthForm();
    }

    function getEmailFormData() {
        return {
            to: getFieldValue('emailTo'),
            cc: getFieldValue('emailCc'),
            subject: getFieldValue('emailSubject'),
            body: getFieldValue('emailBody'),
        };
    }

    function getExecutionRequestData() {
        return {
            year: getFieldValue('year'),
            month: getFieldValue('month'),
            d3_date: getFieldValue('d3_date'),
            d15_date: getFieldValue('d15_date'),
            d25_date: getFieldValue('d25_date'),
            d27_date: getFieldValue('d27_date'),
            target_id: selectedTargetId,
        };
    }

    function getFieldValue(id) {
        const element = document.getElementById(id);

        return element ? element.value : '';
    }

    function syncEmailCustomizationFlag() {
        const flag = document.getElementById('emailCustomized');
        if (!flag) {
            return;
        }

        const current = getEmailFormData();
        const customized = current.to !== (defaultEmailData.to || '')
            || current.cc !== (defaultEmailData.cc || '')
            || current.subject !== (defaultEmailData.subject || '')
            || current.body !== (defaultEmailData.body || '');

        flag.value = customized ? '1' : '0';
    }

    function getSQLContent() {
        return ['sqlRattrapage', 'sqlQuittancement', 'sqlSanteCollective']
            .map(function (id) {
                return getFieldValue(id).trim();
            })
            .filter(function (value) {
                return value !== '';
            })
            .join('\n\n');
    }

    async function copyText(text, message) {
        if (text.trim() === '') {
            showToast('Rien a copier.', 'warning');
            return;
        }

        try {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                await navigator.clipboard.writeText(text);
            } else {
                const element = document.createElement('textarea');
                element.value = text;
                document.body.appendChild(element);
                element.select();
                document.execCommand('copy');
                document.body.removeChild(element);
            }

            showToast(message || 'Copie effectuee.', 'success');
        } catch (error) {
            console.error(error);
            showToast('Impossible de copier le contenu.', 'error');
        }
    }

    function copyToClipboard(elementId, message) {
        const element = document.getElementById(elementId);
        if (!element) {
            showToast('Champ introuvable.', 'error');
            return;
        }

        copyText(element.value, message || 'Contenu copie.');
    }

    function copyAllSQL() {
        copyText(getSQLContent(), 'Les 3 blocs SQL ont ete copies.');
    }

    function copyEmailToClipboard() {
        const email = getEmailFormData();
        const emailText = 'To: ' + email.to + '\n'
            + 'Cc: ' + email.cc + '\n'
            + 'Subject: ' + email.subject + '\n\n'
            + email.body;

        copyText(emailText, 'Email copie au format texte.');
    }

    function downloadSQL() {
        downloadFile(getSQLContent(), buildFileName('sql'), 'text/plain');
    }

    async function executeSqlBlocks() {
        if (!appData.executeUrl || !appData.executeToken) {
            showExecutionStatus('La connexion PostgreSQL n est pas configuree.', 'error');
            return;
        }

        if (!selectedTargetId) {
            showExecutionStatus('Selectionnez une cible BDD avant de lancer les requetes.', 'error');
            return;
        }

        if (!appData.executionConfigured) {
            showExecutionStatus('La cible BDD selectionnee est incomplete.', 'error');
            return;
        }

        const trigger = document.querySelector('[data-action="execute-sql"]');
        if (trigger) {
            trigger.disabled = true;
            trigger.dataset.loading = '1';
            trigger.textContent = 'Execution en cours...';
        }

        showExecutionStatus('Execution des 3 blocs en cours...', 'info');

        try {
            const response = await fetch(appData.executeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _token: appData.executeToken,
                    targetId: selectedTargetId,
                    requestData: getExecutionRequestData(),
                }),
            });

            const payload = await readJsonResponse(response);

            if (!response.ok) {
                throw new Error(payload && payload.message ? payload.message : 'Execution impossible.');
            }

            const executedBlocks = Array.isArray(payload.executedBlocks) ? payload.executedBlocks.join(', ') : '3 blocs';
            showExecutionStatus((payload.message || 'Execution terminee.') + ' Blocs executes : ' + executedBlocks + '.', 'success');
            showToast('Requetes executees avec succes.', 'success');
        } catch (error) {
            console.error(error);
            showExecutionStatus(error.message || 'Execution impossible.', 'error');
            showToast(error.message || 'Execution impossible.', 'error');
        } finally {
            if (trigger) {
                trigger.disabled = !appData.executionConfigured;
                delete trigger.dataset.loading;
                trigger.textContent = 'Executer les 3 requetes';
            }
        }
    }

    async function readJsonResponse(response) {
        const contentType = String(response.headers.get('content-type') || '').toLowerCase();

        if (!contentType.includes('application/json')) {
            const responseText = await response.text();
            throw new Error(buildUnexpectedResponseMessage(response, responseText));
        }

        try {
            return await response.json();
        } catch (error) {
            throw new Error('Le serveur a renvoye un JSON invalide (HTTP ' + response.status + ').');
        }
    }

    function buildUnexpectedResponseMessage(response, responseText) {
        if (response.redirected) {
            return 'Votre session semble avoir expire. Rechargez la page puis reconnectez-vous.';
        }

        if (String(responseText || '').trim().startsWith('<')) {
            return 'Le serveur a renvoye une page HTML inattendue (HTTP ' + response.status + '). Rechargez la page puis reessayez.';
        }

        return 'Le serveur a renvoye une reponse inattendue (HTTP ' + response.status + ').';
    }

    function exportAsJSON() {
        const data = {
            generated_at: new Date().toISOString(),
            dates: appData.dates || {},
            email: getEmailFormData(),
            sql: {
                rattrapage: getFieldValue('sqlRattrapage'),
                quittancement: getFieldValue('sqlQuittancement'),
                sante_collective: getFieldValue('sqlSanteCollective'),
            },
            target: getSelectedTarget(),
        };

        downloadFile(JSON.stringify(data, null, 2), buildFileName('json'), 'application/json');
    }

    function exportAsCSV() {
        if (!appData.dates) {
            showToast('Donnees non disponibles.', 'error');
            return;
        }

        const dates = appData.dates;
        let csv = 'Type de date,Date complete,Date seule,Heure,Description\n';
        csv += 'D3 - Rattrapage,' + csvValue(dates.d3.date_formatted) + ',' + csvValue(dates.d3.date_only) + ',' + csvValue(dates.d3.time) + ',2e vendredi du mois\n';
        csv += 'D15 - Quittancement,' + csvValue(dates.d15.date_formatted) + ',' + csvValue(dates.d15.date_only) + ',' + csvValue(dates.d15.time) + ',Vendredi suivant D3\n';
        csv += 'D25 - Sante collective,' + csvValue(dates.d25.date_formatted) + ',' + csvValue(dates.d25.date_only) + ',' + csvValue(dates.d25.time) + ',Veille de fin de mois\n';
        csv += 'D27 - Ajustement,' + csvValue(dates.d27.date_formatted) + ',' + csvValue(dates.d27.date_only) + ',' + csvValue(dates.d27.time) + ',2 jours avant D25 si possible\n';

        downloadFile(csv, buildFileName('csv'), 'text/csv;charset=utf-8;');
    }

    function openEmailClient() {
        const email = getEmailFormData();
        const subject = encodeURIComponent(email.subject);
        const body = encodeURIComponent(email.body);
        const to = (email.to.split(';')[0] || '').trim();

        window.location.href = 'mailto:' + to
            + '?cc=' + encodeURIComponent(email.cc)
            + '&subject=' + subject
            + '&body=' + body;
    }

    function csvValue(value) {
        const normalized = String(value || '');

        return '"' + normalized.replace(/"/g, '""') + '"';
    }

    function buildFileName(extension) {
        const monthName = getMonthName(appData.month || new Date().getMonth() + 1);
        const year = String(appData.year || new Date().getFullYear());

        return 'quittancements_' + monthName + '_' + year + '.' + extension;
    }

    function getMonthName(month) {
        const months = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];

        return months[Math.max(0, Math.min(11, parseInt(month, 10) - 1))];
    }

    function downloadFile(content, fileName, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.href = url;
        link.download = fileName;
        link.style.display = 'none';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        showToast('Fichier telecharge : ' + fileName, 'success');
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'qg-toast qg-toast-' + (type || 'info');
        toast.textContent = message;
        document.body.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('is-leaving');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, 2600);
    }

    function showExecutionStatus(message, type) {
        const status = document.getElementById('qgExecutionStatus');
        if (!status) {
            return;
        }

        status.hidden = false;
        status.textContent = message;
        status.className = 'qg-execution-status is-' + (type || 'info');
    }

    function hideExecutionStatus() {
        const status = document.getElementById('qgExecutionStatus');
        if (!status) {
            return;
        }

        status.hidden = true;
        status.textContent = '';
        status.className = 'qg-execution-status';
    }

    function normalizeDbTargets(targets) {
        if (!Array.isArray(targets)) {
            return [];
        }

        return targets.map(function (target) {
            if (!target || typeof target !== 'object') {
                return null;
            }

            const normalized = {
                id: String(target.id || '').trim(),
                label: String(target.label || '').trim(),
                host: String(target.host || '').trim(),
                port: parseInt(target.port, 10) || 0,
                database: String(target.database || '').trim(),
                username: String(target.username || '').trim(),
                passwordConfigured: Boolean(target.passwordConfigured),
                passwordPreview: String(target.passwordPreview || '').trim(),
                isBuiltIn: Boolean(target.isBuiltIn),
            };

            return normalized.id && normalized.host && normalized.database && normalized.username ? normalized : null;
        }).filter(Boolean);
    }

    function resolveSelectedTargetId(targetId) {
        const requestedId = String(targetId || '').trim();
        const found = dbTargets.find(function (target) {
            return target.id === requestedId;
        });

        return found ? found.id : (dbTargets[0] ? dbTargets[0].id : '');
    }

    function getSelectedTarget() {
        return dbTargets.find(function (target) {
            return target.id === selectedTargetId;
        }) || null;
    }

    function syncSelectedTarget(targetId) {
        selectedTargetId = resolveSelectedTargetId(targetId);
        syncTargetInput();
        renderDbTargetSelect();
        renderDbTargetsList();
        updateExecutionTargetUi();
        updatePageUrlForTarget();
    }

    function syncTargetInput() {
        const hiddenInput = document.getElementById('target_id');
        if (hiddenInput) {
            hiddenInput.value = selectedTargetId;
        }

        const targetSelect = document.getElementById('qgTargetSelect');
        if (targetSelect) {
            targetSelect.value = selectedTargetId;
        }
    }

    function renderDbTargetSelect() {
        const select = document.getElementById('qgTargetSelect');
        if (!select) {
            return;
        }

        select.innerHTML = '';

        if (dbTargets.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Aucune cible configuree';
            select.appendChild(option);
            selectedTargetId = '';
            return;
        }

        selectedTargetId = resolveSelectedTargetId(selectedTargetId);
        dbTargets.forEach(function (target) {
            const option = document.createElement('option');
            option.value = target.id;
            option.textContent = target.label + ' - ' + target.host + ':' + target.port + '/' + target.database;
            option.selected = target.id === selectedTargetId;
            select.appendChild(option);
        });
    }

    function renderDbTargetsList() {
        const list = document.getElementById('qgDbTargetsList');
        if (!list) {
            return;
        }

        list.innerHTML = dbTargets.length === 0
            ? '<div class="qg-target-empty">Aucun serveur enregistre pour le moment.</div>'
            : dbTargets.map(function (target) {
                const badge = target.isBuiltIn
                    ? '<span class="qg-target-badge">Par defaut</span>'
                    : (target.id === selectedTargetId ? '<span class="qg-target-badge is-selected">Active</span>' : '');
                const deleteButton = target.isBuiltIn
                    ? ''
                    : '<button type="button" class="qg-button qg-button-danger" data-target-action="delete" data-target-id="' + escapeHtml(target.id) + '">Supprimer</button>';

                return '<article class="qg-target-item' + (target.id === selectedTargetId ? ' is-active' : '') + '">'
                    + '<div class="qg-target-header"><div class="qg-target-title-wrap"><strong>' + escapeHtml(target.label) + '</strong>' + badge + '</div>'
                    + '<div class="qg-target-actions"><button type="button" class="qg-button qg-button-ghost" data-target-action="use" data-target-id="' + escapeHtml(target.id) + '">' + (target.id === selectedTargetId ? 'Utilise' : 'Utiliser') + '</button>' + deleteButton + '</div></div>'
                    + '<div class="qg-target-meta"><span>' + escapeHtml(target.host) + ':' + escapeHtml(String(target.port)) + '/' + escapeHtml(target.database) + '</span><span>Utilisateur : ' + escapeHtml(target.username) + '</span><span>Mot de passe : ' + escapeHtml(target.passwordConfigured ? (target.passwordPreview || 'enregistre') : 'non renseigne') + '</span></div>'
                    + '</article>';
            }).join('');
    }

    function updateExecutionTargetUi() {
        const target = getSelectedTarget();
        const executeButton = document.querySelector('[data-action="execute-sql"]');
        const hint = document.getElementById('qgExecutionHint');
        const configured = Boolean(target && target.host && target.port > 0 && target.database && target.username);

        appData.executionConfigured = configured;
        appData.executionTarget = target || null;

        if (executeButton) {
            executeButton.disabled = !configured;
        }

        if (hint) {
            hint.textContent = configured
                ? 'Cible actuelle : ' + (target.label ? target.label + ' - ' : '') + target.host + ':' + target.port + '/' + target.database
                : 'Selectionnez ou ajoutez une cible BDD pour executer les requetes.';
        }
    }

    function updatePageUrlForTarget() {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        const url = new URL(window.location.href);
        if (selectedTargetId) {
            url.searchParams.set('target', selectedTargetId);
        } else {
            url.searchParams.delete('target');
        }

        window.history.replaceState({}, '', url.toString());
    }

    function openDbSettingsModal() {
        const modal = document.getElementById('qgDbSettingsModal');
        if (!modal) {
            return;
        }

        hideSettingsFeedback();
        renderDbTargetsList();
        modal.hidden = false;
        document.body.classList.add('qg-modal-open');
    }

    function closeDbSettingsModal() {
        const modal = document.getElementById('qgDbSettingsModal');
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('qg-modal-open');
    }

    async function submitDbTargetForm() {
        if (!appData.settingsUrl || !appData.settingsToken) {
            showSettingsFeedback('Le parametrage BDD est indisponible.', 'error');
            return;
        }

        const form = document.getElementById('qgDbTargetForm');
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        if (submitButton) {
            submitButton.disabled = true;
        }

        showSettingsFeedback('Enregistrement du serveur BDD...', 'info');

        try {
            const response = await fetch(appData.settingsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': appData.settingsToken,
                },
                body: JSON.stringify({
                    action: 'add_target',
                    selectedTargetId: selectedTargetId,
                    label: getFieldValue('qgDbTargetLabel'),
                    host: getFieldValue('qgDbTargetHost'),
                    port: getFieldValue('qgDbTargetPort') || '5432',
                    database: getFieldValue('qgDbTargetDatabase'),
                    username: getFieldValue('qgDbTargetUsername'),
                    password: getFieldValue('qgDbTargetPassword'),
                }),
            });

            const payload = await readJsonResponse(response);
            if (!response.ok) {
                throw new Error(payload && payload.message ? payload.message : 'Impossible d enregistrer ce serveur.');
            }

            applyTargetsPayload(payload);
            if (form) {
                form.reset();
            }
            const portField = document.getElementById('qgDbTargetPort');
            if (portField) {
                portField.value = '5432';
            }
            showSettingsFeedback(payload.message || 'Serveur BDD ajoute.', 'success');
            showToast('Serveur BDD ajoute.', 'success');
        } catch (error) {
            console.error(error);
            showSettingsFeedback(error.message || 'Impossible d enregistrer ce serveur.', 'error');
            showToast(error.message || 'Impossible d enregistrer ce serveur.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    async function deleteDbTarget(targetId) {
        const target = dbTargets.find(function (item) {
            return item.id === String(targetId || '').trim();
        });
        if (!target || !window.confirm('Supprimer le serveur "' + target.label + '" ?')) {
            return;
        }

        showSettingsFeedback('Suppression du serveur BDD...', 'info');

        try {
            const response = await fetch(appData.settingsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': appData.settingsToken,
                },
                body: JSON.stringify({
                    action: 'delete_target',
                    targetId: target.id,
                    selectedTargetId: selectedTargetId,
                }),
            });

            const payload = await readJsonResponse(response);
            if (!response.ok) {
                throw new Error(payload && payload.message ? payload.message : 'Impossible de supprimer ce serveur.');
            }

            applyTargetsPayload(payload);
            showSettingsFeedback(payload.message || 'Serveur BDD supprime.', 'success');
            showToast('Serveur BDD supprime.', 'success');
        } catch (error) {
            console.error(error);
            showSettingsFeedback(error.message || 'Impossible de supprimer ce serveur.', 'error');
            showToast(error.message || 'Impossible de supprimer ce serveur.', 'error');
        }
    }

    function applyTargetsPayload(payload) {
        dbTargets = normalizeDbTargets(payload && payload.targets ? payload.targets : []);
        selectedTargetId = resolveSelectedTargetId(payload && payload.selectedTargetId ? payload.selectedTargetId : selectedTargetId);
        syncTargetInput();
        renderDbTargetSelect();
        renderDbTargetsList();
        updateExecutionTargetUi();
        updatePageUrlForTarget();
        hideExecutionStatus();
    }

    function showSettingsFeedback(message, type) {
        const feedback = document.getElementById('qgDbSettingsFeedback');
        if (!feedback) {
            return;
        }

        feedback.hidden = false;
        feedback.textContent = message;
        feedback.className = 'qg-settings-feedback is-' + (type || 'info');
    }

    function hideSettingsFeedback() {
        const feedback = document.getElementById('qgDbSettingsFeedback');
        if (!feedback) {
            return;
        }

        feedback.hidden = true;
        feedback.textContent = '';
        feedback.className = 'qg-settings-feedback';
    }

    function escapeHtml(value) {
        return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
})();
