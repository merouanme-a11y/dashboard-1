(function () {
    'use strict';

    const appData = window.__QG_APP__ || {};
    const defaultEmailData = appData.defaultEmailData || {};

    document.addEventListener('DOMContentLoaded', function () {
        initializeEventListeners();
        syncEmailCustomizationFlag();
    });

    function initializeEventListeners() {
        const yearSelect = document.getElementById('year');
        const monthSelect = document.getElementById('month');
        const emailFieldIds = ['emailTo', 'emailCc', 'emailSubject', 'emailBody'];

        if (yearSelect) {
            yearSelect.addEventListener('change', updatePage);
        }

        if (monthSelect) {
            monthSelect.addEventListener('change', updatePage);
        }

        emailFieldIds.forEach(function (id) {
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
        const months = [
            'janvier',
            'fevrier',
            'mars',
            'avril',
            'mai',
            'juin',
            'juillet',
            'aout',
            'septembre',
            'octobre',
            'novembre',
            'decembre',
        ];

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
})();
