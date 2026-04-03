document.addEventListener('DOMContentLoaded', function () {
    const typeEncaissement = document.getElementById('type_encaissement');
    const chequeBox = document.getElementById('cheque-box');
    const chequeInput = chequeBox ? chequeBox.querySelector('input') : null;
    const typeAffaire = document.getElementById('type_affaire');
    const risque = document.getElementById('risque');
    const typeAffaireHelper = document.getElementById('type-affaire-helper');
    const dateReglementInput = document.getElementById('date_reglement');
    const typeAffaireRules = window.ldcTypeAffaireRules || {};
    const typeEncaissementOptionsByAffaire = window.ldcTypeEncaissementOptionsByAffaire || {};
    const anticipationMonthsWindow = Number(window.ldcAnticipationMonthsWindow || 24);
    const caisseConfig = window.ldcCaisseConfig || {};
    const form = document.querySelector('#saisie-form form');
    const saisieFormPanel = document.getElementById('saisie-form');
    const tableToggleButton = document.getElementById('toggle-table-columns');
    const tableScroll = document.getElementById('prod-table-scroll');
    const tablePanel = tableScroll ? tableScroll.closest('.panel-table') : null;
    const monthPickers = Array.from(document.querySelectorAll('[data-month-picker]'));
    const fondCaisseButton = document.getElementById('edit-fond-caisse');
    const fondCaisseModal = document.getElementById('fond-caisse-modal');
    const fondCaisseModalInput = document.getElementById('fond-caisse-modal-input');
    const fondCaisseConfirmButton = document.getElementById('fond-caisse-confirm');
    const fondCaisseCancelButton = document.getElementById('fond-caisse-cancel');
    const fondCaisseUpdateForm = document.getElementById('fond-caisse-update-form');
    const fondCaisseUpdateValue = document.getElementById('fond_caisse_update_value');
    const fondCaisseHiddenInput = document.getElementById('fond_caisse_debut_journee');
    const fondCaisseConfirmedInput = document.getElementById('fond_caisse_confirme');
    const openNouveauReglementButton = document.getElementById('open-nouveau-reglement');
    const finJourneeForms = Array.from(document.querySelectorAll('.fin-journee-form'));
    const finJourneeModal = document.getElementById('fin-journee-modal');
    const finJourneeModalError = document.getElementById('fin-journee-modal-error');
    const finJourneeFondFin = document.getElementById('fin-journee-fond-fin');
    const finJourneeTotalEspeces = document.getElementById('fin-journee-total-especes');
    const finJourneeTotalCheques = document.getElementById('fin-journee-total-cheques');
    const finJourneeModeButtons = Array.from(document.querySelectorAll('[data-fin-mode-value]'));
    const finJourneeModeWithButton = document.getElementById('fin-journee-mode-with');
    const finJourneeDepositSection = document.getElementById('fin-journee-deposit-section');
    const finJourneeEspecesBlock = document.getElementById('fin-journee-especes-block');
    const finJourneeEspecesToggle = document.getElementById('fin-journee-depot-especes');
    const finJourneeEspecesFields = document.getElementById('fin-journee-especes-fields');
    const finJourneeEspecesAmount = document.getElementById('fin-journee-especes-amount');
    const finJourneeEspecesRemise = document.getElementById('fin-journee-especes-remise');
    const finJourneeChequesBlock = document.getElementById('fin-journee-cheques-block');
    const finJourneeChequesToggle = document.getElementById('fin-journee-depot-cheques');
    const finJourneeChequesFields = document.getElementById('fin-journee-cheques-fields');
    const finJourneeChequesAmount = document.getElementById('fin-journee-cheques-amount');
    const finJourneeChequesRemise = document.getElementById('fin-journee-cheques-remise');
    const finJourneeCancelButton = document.getElementById('fin-journee-cancel');
    const finJourneeConfirmButton = document.getElementById('fin-journee-confirm');
    const modalCloseTriggers = Array.from(document.querySelectorAll('[data-close-ldc-modal]'));
    const printButtons = Array.from(document.querySelectorAll('[data-print-filter]'));
    const remiseForm = document.getElementById('caisse-remise-form');
    const remiseInputs = Array.from(document.querySelectorAll('[data-auto-submit-remise]'));
    const attachmentInput = document.querySelector('[data-attachment-input]');
    const attachmentCameraInput = document.querySelector('[data-attachment-camera-input]');
    const attachmentDropzone = document.querySelector('[data-attachment-dropzone]');
    const attachmentTrigger = document.querySelector('[data-attachment-trigger]');
    const attachmentCameraTrigger = document.querySelector('[data-attachment-camera-trigger]');
    const attachmentSelectedList = document.querySelector('[data-attachment-selected-list]');
    let fondCaisseModalMode = 'manual';
    let currentFinJourneeForm = null;
    let finJourneeMode = 'without_deposit';
    let remiseAutosaveTimer = null;
    let selectedAttachmentFiles = attachmentInput ? Array.from(attachmentInput.files || []) : [];

    const monthLabels = [
        'Janvier',
        'Fevrier',
        'Mars',
        'Avril',
        'Mai',
        'Juin',
        'Juillet',
        'Aout',
        'Septembre',
        'Octobre',
        'Novembre',
        'Decembre',
    ];

    function setHidden(element, shouldHide) {
        if (!element) {
            return;
        }

        element.classList.toggle('is-hidden', shouldHide);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseMoneyValue(value) {
        const normalized = String(value || '').replace(/\s+/g, '').replace(',', '.');
        const parsedValue = Number.parseFloat(normalized);

        return Number.isFinite(parsedValue) ? parsedValue : 0;
    }

    function formatEuroValue(value) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value) + ' \u20AC';
    }

    function normalizeMoneyForField(value) {
        return parseMoneyValue(value).toFixed(2);
    }

    function formatFileSize(size) {
        const units = ['o', 'Ko', 'Mo', 'Go'];
        let value = Number(size) || 0;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }

        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: unitIndex === 0 ? 0 : 1,
            maximumFractionDigits: unitIndex === 0 ? 0 : 1,
        }).format(value) + ' ' + units[unitIndex];
    }

    function getAttachmentFileKey(file) {
        return [file.name, file.size, file.lastModified].join('::');
    }

    function syncAttachmentInputFiles() {
        if (!attachmentInput || typeof DataTransfer === 'undefined') {
            return;
        }

        const transfer = new DataTransfer();
        selectedAttachmentFiles.forEach(function (file) {
            transfer.items.add(file);
        });
        attachmentInput.files = transfer.files;
    }

    function renderSelectedAttachments() {
        if (!attachmentSelectedList) {
            return;
        }

        attachmentSelectedList.innerHTML = '';
        attachmentSelectedList.classList.toggle('is-hidden', selectedAttachmentFiles.length === 0);

        selectedAttachmentFiles.forEach(function (file, index) {
            const row = document.createElement('div');
            row.className = 'attachment-selected-item';

            const text = document.createElement('div');
            text.className = 'attachment-selected-text';
            text.innerHTML = '<strong>' + escapeHtml(file.name) + '</strong><span>' + escapeHtml(formatFileSize(file.size)) + '</span>';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'attachment-selected-remove';
            button.textContent = 'Retirer';
            button.setAttribute('data-attachment-remove-index', String(index));

            row.appendChild(text);
            row.appendChild(button);
            attachmentSelectedList.appendChild(row);
        });
    }

    function appendAttachmentFiles(files) {
        if (!files || files.length === 0) {
            return;
        }

        const knownKeys = new Set(selectedAttachmentFiles.map(getAttachmentFileKey));

        files.forEach(function (file) {
            const fileKey = getAttachmentFileKey(file);
            if (!knownKeys.has(fileKey)) {
                selectedAttachmentFiles.push(file);
                knownKeys.add(fileKey);
            }
        });

        syncAttachmentInputFiles();
        renderSelectedAttachments();
    }

    function normalizeMonthValues(value) {
        const rawValues = Array.isArray(value) ? value : String(value || '').split(',');
        const validValues = rawValues
            .map(function (rawValue) { return rawValue.trim(); })
            .filter(function (rawValue) { return /^\d{4}-\d{2}$/.test(rawValue); });

        return Array.from(new Set(validValues)).sort();
    }

    function formatMonthLabel(value) {
        const match = /^(\d{4})-(\d{2})$/.exec(value);
        if (!match) {
            return value;
        }

        const year = match[1].slice(-2);
        const monthIndex = Number(match[2]) - 1;

        return (monthLabels[monthIndex] || value) + '-' + year;
    }

    function buildMonthSummaryHtml(values) {
        if (!values.length) {
            return 'Selectionner un ou plusieurs mois';
        }

        const labels = values.map(function (value) {
            return escapeHtml(formatMonthLabel(value));
        });

        return '<strong>' + values.length + ' mois</strong> : ' + labels.join(', ');
    }

    function getMonthOptions(baseDateValue, selectedValues) {
        const values = new Set(selectedValues);
        const referenceDate = baseDateValue ? new Date(baseDateValue + 'T00:00:00') : new Date();
        const safeDate = Number.isNaN(referenceDate.getTime()) ? new Date() : referenceDate;
        const startDate = new Date(safeDate.getFullYear(), safeDate.getMonth(), 1);

        for (let index = 0; index < anticipationMonthsWindow; index += 1) {
            const currentDate = new Date(startDate.getFullYear(), startDate.getMonth() + index, 1);
            const value = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
            values.add(value);
        }

        return Array.from(values)
            .sort()
            .map(function (value) {
                return {
                    value: value,
                    label: formatMonthLabel(value),
                };
            });
    }

    function closeMonthPicker(picker) {
        const trigger = picker.querySelector('[data-month-picker-trigger]');
        picker.classList.remove('is-open');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    }

    function closeAllMonthPickers(exceptPicker) {
        monthPickers.forEach(function (picker) {
            if (picker !== exceptPicker) {
                closeMonthPicker(picker);
            }
        });
    }

    function syncFondCaisseFields(value) {
        const normalizedValue = normalizeMoneyForField(value);
        const parsedValue = parseMoneyValue(normalizedValue);

        if (fondCaisseButton) {
            fondCaisseButton.dataset.fondCaisseValue = normalizedValue;
            fondCaisseButton.textContent = formatEuroValue(parsedValue);
        }

        if (fondCaisseModalInput) {
            fondCaisseModalInput.value = normalizedValue;
        }

        if (fondCaisseUpdateValue) {
            fondCaisseUpdateValue.value = normalizedValue;
        }

        if (fondCaisseHiddenInput) {
            fondCaisseHiddenInput.value = normalizedValue;
        }

        caisseConfig.fondCaisseDebut = normalizedValue;
    }

    function syncBodyModalState() {
        const hasOpenModal = [fondCaisseModal, finJourneeModal].some(function (modal) {
            return modal && !modal.classList.contains('is-hidden');
        });

        document.body.classList.toggle('has-modal-open', hasOpenModal);
    }

    function openFondCaisseModal(mode) {
        if (!fondCaisseModal || !fondCaisseModalInput) {
            return;
        }

        fondCaisseModalMode = mode;
        syncFondCaisseFields(caisseConfig.fondCaisseDebut || fondCaisseButton?.dataset.fondCaisseValue || 0);
        fondCaisseModal.classList.remove('is-hidden');
        fondCaisseModal.setAttribute('aria-hidden', 'false');
        syncBodyModalState();

        window.setTimeout(function () {
            fondCaisseModalInput.focus();
            fondCaisseModalInput.select();
        }, 0);
    }

    function closeFondCaisseModal() {
        if (!fondCaisseModal) {
            return;
        }

        fondCaisseModal.classList.add('is-hidden');
        fondCaisseModal.setAttribute('aria-hidden', 'true');
        syncBodyModalState();
    }

    function setFinJourneeError(message) {
        if (!finJourneeModalError) {
            return;
        }

        finJourneeModalError.textContent = message || '';
        finJourneeModalError.classList.toggle('is-hidden', !message);
    }

    function setFinJourneeMode(mode) {
        finJourneeMode = mode === 'with_deposit' ? 'with_deposit' : 'without_deposit';

        finJourneeModeButtons.forEach(function (button) {
            const isActive = button.getAttribute('data-fin-mode-value') === finJourneeMode;
            button.classList.toggle('is-active', isActive);
        });

        if (finJourneeDepositSection) {
            finJourneeDepositSection.classList.toggle('is-hidden', finJourneeMode !== 'with_deposit');
        }
    }

    function toggleFinJourneeDepositFields() {
        const totalEspeces = parseMoneyValue(caisseConfig.totalEspeces || 0);
        const totalCheques = parseMoneyValue(caisseConfig.totalCheques || 0);

        if (finJourneeEspecesBlock) {
            finJourneeEspecesBlock.classList.toggle('is-hidden', !(totalEspeces > 0));
        }
        if (finJourneeChequesBlock) {
            finJourneeChequesBlock.classList.toggle('is-hidden', !(totalCheques > 0));
        }

        if (finJourneeEspecesFields) {
            const showEspecesFields = Boolean(finJourneeEspecesToggle && finJourneeEspecesToggle.checked && totalEspeces > 0);
            finJourneeEspecesFields.classList.toggle('is-hidden', !showEspecesFields);
        }

        if (finJourneeChequesFields) {
            const showChequesFields = Boolean(finJourneeChequesToggle && finJourneeChequesToggle.checked && totalCheques > 0);
            finJourneeChequesFields.classList.toggle('is-hidden', !showChequesFields);
        }
    }

    function openFinJourneeModal(finJourneeForm) {
        if (!finJourneeModal) {
            return;
        }

        currentFinJourneeForm = finJourneeForm;
        if (finJourneeConfirmButton) {
            finJourneeConfirmButton.disabled = false;
        }
        setFinJourneeError('');

        const totalEspeces = parseMoneyValue(caisseConfig.totalEspeces || 0);
        const totalCheques = parseMoneyValue(caisseConfig.totalCheques || 0);
        const visibleNumRemiseEspecesInput = document.querySelector('#caisse-remise-form [name="num_remise_especes"]');
        const visibleNumRemiseChequeInput = document.querySelector('#caisse-remise-form [name="num_remise_cheque"]');

        if (finJourneeFondFin) {
            finJourneeFondFin.textContent = finJourneeForm.getAttribute('data-fond-fin-label') || formatEuroValue(parseMoneyValue(caisseConfig.fondCaisseFin || 0));
        }
        if (finJourneeTotalEspeces) {
            finJourneeTotalEspeces.textContent = formatEuroValue(totalEspeces);
        }
        if (finJourneeTotalCheques) {
            finJourneeTotalCheques.textContent = formatEuroValue(totalCheques);
        }

        if (finJourneeEspecesToggle) {
            finJourneeEspecesToggle.checked = false;
        }
        if (finJourneeChequesToggle) {
            finJourneeChequesToggle.checked = false;
        }
        if (finJourneeEspecesAmount) {
            finJourneeEspecesAmount.value = totalEspeces > 0 ? totalEspeces.toFixed(2) : '';
        }
        if (finJourneeEspecesRemise) {
            finJourneeEspecesRemise.value = (visibleNumRemiseEspecesInput && visibleNumRemiseEspecesInput.value.trim()) || String(caisseConfig.numRemiseEspeces || '');
        }
        if (finJourneeChequesAmount) {
            finJourneeChequesAmount.textContent = formatEuroValue(totalCheques);
        }
        if (finJourneeChequesRemise) {
            finJourneeChequesRemise.value = (visibleNumRemiseChequeInput && visibleNumRemiseChequeInput.value.trim()) || String(caisseConfig.numRemiseCheque || '');
        }

        if (finJourneeModeWithButton) {
            finJourneeModeWithButton.disabled = !(totalEspeces > 0 || totalCheques > 0);
        }

        setFinJourneeMode('without_deposit');
        toggleFinJourneeDepositFields();

        finJourneeModal.classList.remove('is-hidden');
        finJourneeModal.setAttribute('aria-hidden', 'false');
        syncBodyModalState();

        window.setTimeout(function () {
            const activeButton = finJourneeModeButtons.find(function (button) {
                return button.classList.contains('is-active');
            });

            if (activeButton) {
                activeButton.focus();
            }
        }, 0);
    }

    function closeFinJourneeModal() {
        if (!finJourneeModal) {
            return;
        }

        finJourneeModal.classList.add('is-hidden');
        finJourneeModal.setAttribute('aria-hidden', 'true');
        if (finJourneeConfirmButton) {
            finJourneeConfirmButton.disabled = false;
        }
        currentFinJourneeForm = null;
        setFinJourneeError('');
        syncBodyModalState();
    }

    function showSaisieForm() {
        if (!saisieFormPanel) {
            return;
        }

        const preferredField = document.getElementById('type_affaire');
        const firstField = preferredField && !preferredField.disabled
            ? preferredField
            : saisieFormPanel.querySelector(
                'select:not(:disabled), input:not([type="hidden"]):not(:disabled), textarea:not(:disabled)'
            );

        saisieFormPanel.classList.remove('is-hidden');

        window.setTimeout(function () {
            saisieFormPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (firstField) {
                firstField.focus();
            }
        }, 0);
    }

    function confirmFondCaisse() {
        const normalizedValue = normalizeMoneyForField(fondCaisseModalInput ? fondCaisseModalInput.value : 0);

        syncFondCaisseFields(normalizedValue);
        closeFondCaisseModal();

        if (fondCaisseModalMode === 'manual') {
            if (fondCaisseUpdateForm) {
                fondCaisseUpdateForm.submit();
            }
            return;
        }

        if (fondCaisseConfirmedInput) {
            fondCaisseConfirmedInput.value = '1';
        }

        caisseConfig.shouldConfirmOnFirstEntry = false;

        if (fondCaisseModalMode === 'open_form') {
            showSaisieForm();
        }
    }

    function syncMonthPickerValue(picker) {
        const input = picker.querySelector('[data-month-picker-input]');
        const label = picker.querySelector('[data-month-picker-label]');
        const checkedValues = Array.from(picker.querySelectorAll('[data-month-picker-options] input[type="checkbox"]:checked'))
            .map(function (checkbox) { return checkbox.value; });
        const normalizedValues = normalizeMonthValues(checkedValues);

        if (input) {
            input.value = normalizedValues.join(',');
        }

        if (label) {
            label.innerHTML = buildMonthSummaryHtml(normalizedValues);
        }
    }

    function renderMonthPicker(picker) {
        const input = picker.querySelector('[data-month-picker-input]');
        const optionsContainer = picker.querySelector('[data-month-picker-options]');
        const label = picker.querySelector('[data-month-picker-label]');
        const selectedValues = normalizeMonthValues(input ? input.value : '');
        const options = getMonthOptions(dateReglementInput ? dateReglementInput.value : '', selectedValues);

        if (!optionsContainer) {
            return;
        }

        optionsContainer.innerHTML = '';

        options.forEach(function (option) {
            const optionLabel = document.createElement('label');
            optionLabel.className = 'multi-select-option';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = option.value;
            checkbox.checked = selectedValues.includes(option.value);

            if (input && input.disabled) {
                checkbox.disabled = true;
            }

            const text = document.createElement('span');
            text.textContent = option.label;

            optionLabel.appendChild(checkbox);
            optionLabel.appendChild(text);
            optionsContainer.appendChild(optionLabel);
        });

        if (label) {
            label.innerHTML = buildMonthSummaryHtml(selectedValues);
        }
    }

    function initializeMonthPickers() {
        monthPickers.forEach(function (picker) {
            const trigger = picker.querySelector('[data-month-picker-trigger]');
            const optionsContainer = picker.querySelector('[data-month-picker-options]');

            renderMonthPicker(picker);

            if (trigger) {
                trigger.addEventListener('click', function () {
                    if (trigger.disabled) {
                        return;
                    }

                    const isOpen = picker.classList.contains('is-open');
                    closeAllMonthPickers(picker);
                    picker.classList.toggle('is-open', !isOpen);
                    trigger.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
                });
            }

            if (optionsContainer) {
                optionsContainer.addEventListener('change', function (event) {
                    if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') {
                        syncMonthPickerValue(picker);
                    }
                });
            }
        });
    }

    function syncAllMonthPickers() {
        monthPickers.forEach(function (picker) {
            renderMonthPicker(picker);
        });
    }

    function submitRemisesIfChanged() {
        if (!remiseForm || remiseInputs.length === 0) {
            return;
        }

        if (remiseAutosaveTimer) {
            window.clearTimeout(remiseAutosaveTimer);
            remiseAutosaveTimer = null;
        }

        const hasChanges = remiseInputs.some(function (input) {
            return input.value.trim() !== (input.dataset.lastSubmittedValue || '');
        });

        if (!hasChanges) {
            return;
        }

        remiseInputs.forEach(function (input) {
            input.dataset.lastSubmittedValue = input.value.trim();
        });

        if (typeof remiseForm.requestSubmit === 'function') {
            remiseForm.requestSubmit();
            return;
        }

        remiseForm.submit();
    }

    function getTypeEncaissementOptions(selectedTypeAffaire) {
        return typeEncaissementOptionsByAffaire[selectedTypeAffaire] || typeEncaissementOptionsByAffaire[''] || [];
    }

    function refreshTypeEncaissementOptions() {
        if (!typeEncaissement) {
            return;
        }

        const currentValue = typeEncaissement.value;
        const selectedTypeAffaire = typeAffaire ? typeAffaire.value : '';
        const options = getTypeEncaissementOptions(selectedTypeAffaire);

        typeEncaissement.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '- Selectionner -';
        typeEncaissement.appendChild(placeholder);

        options.forEach(function (optionValue) {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue;
            if (optionValue === currentValue) {
                option.selected = true;
            }
            typeEncaissement.appendChild(option);
        });

        if (!options.includes(currentValue)) {
            typeEncaissement.value = '';
        }
    }

    function refreshSequentialSelectors() {
        const hasTypeAffaire = Boolean(typeAffaire && typeAffaire.value);
        const hasRisque = Boolean(risque && risque.value);

        if (risque) {
            risque.disabled = !hasTypeAffaire;
        }

        if (typeEncaissement) {
            typeEncaissement.disabled = !(hasTypeAffaire && hasRisque);
        }
    }

    function toggleCheque() {
        if (!typeEncaissement || !chequeBox) {
            return;
        }

        const shouldShow = typeEncaissement.value === 'Ch?que' || typeEncaissement.value === 'CB';
        setHidden(chequeBox, !shouldShow);
        chequeBox.classList.toggle('is-required', shouldShow);

        if (chequeInput) {
            chequeInput.disabled = !shouldShow;
            chequeInput.required = shouldShow;
        }
    }

    function refreshConditionalSections() {
        document.querySelectorAll('[data-conditional-section]').forEach(function (section) {
            const hasVisibleField = Array.from(section.querySelectorAll('[data-conditional-field]')).some(function (fieldWrapper) {
                return !fieldWrapper.classList.contains('is-hidden');
            });

            setHidden(section, !hasVisibleField);
        });
    }

    function toggleTypeAffaireFields() {
        const selectedType = typeAffaire ? typeAffaire.value : '';
        const selectedRule = typeAffaireRules[selectedType] || { visible_fields: [], required_fields: [], helper: '' };
        const visibleFields = new Set(selectedRule.visible_fields || []);
        const requiredFields = new Set(selectedRule.required_fields || []);

        document.querySelectorAll('[data-conditional-field]').forEach(function (fieldWrapper) {
            const fieldName = fieldWrapper.getAttribute('data-conditional-field');
            const shouldShow = visibleFields.has(fieldName);
            const shouldRequire = shouldShow && requiredFields.has(fieldName);

            setHidden(fieldWrapper, !shouldShow);
            fieldWrapper.classList.toggle('is-required', shouldRequire);

            fieldWrapper.querySelectorAll('input, select, textarea, button').forEach(function (input) {
                input.disabled = !shouldShow;
                if (input instanceof HTMLInputElement && input.type !== 'checkbox' && input.type !== 'button') {
                    input.required = shouldRequire;
                }
            });
        });

        if (typeAffaireHelper) {
            typeAffaireHelper.textContent = selectedRule.helper || '';
            setHidden(typeAffaireHelper, !selectedRule.helper);
        }

        refreshTypeEncaissementOptions();
        refreshSequentialSelectors();
        toggleCheque();
        refreshConditionalSections();
        syncAllMonthPickers();
    }

    function clearPrintFilter() {
        document.documentElement.removeAttribute('data-ldc-print-filter');
    }

    initializeMonthPickers();

    if (typeEncaissement) {
        typeEncaissement.addEventListener('change', toggleCheque);
        toggleCheque();
    }

    if (typeAffaire) {
        typeAffaire.addEventListener('change', toggleTypeAffaireFields);
        toggleTypeAffaireFields();
    } else {
        refreshTypeEncaissementOptions();
    }

    if (risque) {
        risque.addEventListener('change', function () {
            refreshSequentialSelectors();
            toggleCheque();
        });
    }

    if (dateReglementInput) {
        dateReglementInput.addEventListener('change', syncAllMonthPickers);
    }

    if (attachmentTrigger && attachmentInput) {
        attachmentTrigger.addEventListener('click', function () {
            attachmentInput.click();
        });
    }

    if (attachmentCameraTrigger && attachmentCameraInput) {
        attachmentCameraTrigger.addEventListener('click', function () {
            attachmentCameraInput.click();
        });
    }

    if (attachmentInput) {
        attachmentInput.addEventListener('change', function () {
            appendAttachmentFiles(Array.from(attachmentInput.files || []));
        });
    }

    if (attachmentCameraInput) {
        attachmentCameraInput.addEventListener('change', function () {
            appendAttachmentFiles(Array.from(attachmentCameraInput.files || []));
            attachmentCameraInput.value = '';
        });
    }

    if (attachmentDropzone && attachmentInput) {
        attachmentDropzone.addEventListener('click', function (event) {
            if (event.target.closest('[data-attachment-trigger], [data-attachment-camera-trigger]')) {
                return;
            }

            attachmentInput.click();
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            attachmentDropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                attachmentDropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
            attachmentDropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                attachmentDropzone.classList.remove('is-dragover');
            });
        });

        attachmentDropzone.addEventListener('drop', function (event) {
            const files = Array.from((event.dataTransfer && event.dataTransfer.files) || []);
            appendAttachmentFiles(files);
        });

        attachmentDropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                attachmentInput.click();
            }
        });
    }

    if (attachmentSelectedList) {
        attachmentSelectedList.addEventListener('click', function (event) {
            const removeButton = event.target.closest('[data-attachment-remove-index]');
            if (!removeButton) {
                return;
            }

            const index = Number(removeButton.getAttribute('data-attachment-remove-index'));
            if (!Number.isInteger(index) || index < 0) {
                return;
            }

            selectedAttachmentFiles = selectedAttachmentFiles.filter(function (_file, fileIndex) {
                return fileIndex !== index;
            });

            syncAttachmentInputFiles();
            renderSelectedAttachments();
        });
    }

    if (remiseInputs.length > 0) {
        remiseInputs.forEach(function (input) {
            input.dataset.lastSubmittedValue = input.value.trim();

            input.addEventListener('input', function () {
                if (remiseAutosaveTimer) {
                    window.clearTimeout(remiseAutosaveTimer);
                }

                remiseAutosaveTimer = window.setTimeout(function () {
                    submitRemisesIfChanged();
                }, 500);
            });

            input.addEventListener('change', submitRemisesIfChanged);
            input.addEventListener('blur', submitRemisesIfChanged);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitRemisesIfChanged();
                }
            });
        });
    }

    if (fondCaisseButton) {
        fondCaisseButton.addEventListener('click', function () {
            openFondCaisseModal('manual');
        });
    }

    if (fondCaisseConfirmButton) {
        fondCaisseConfirmButton.addEventListener('click', confirmFondCaisse);
    }

    if (fondCaisseCancelButton) {
        fondCaisseCancelButton.addEventListener('click', closeFondCaisseModal);
    }

    if (fondCaisseModal) {
        fondCaisseModal.addEventListener('click', function (event) {
            if (event.target === fondCaisseModal || event.target.closest('[data-close-ldc-modal="fond"]')) {
                closeFondCaisseModal();
            }
        });
    }

    if (openNouveauReglementButton) {
        openNouveauReglementButton.addEventListener('click', function () {
            if (openNouveauReglementButton.disabled || caisseConfig.isDayClosed) {
                return;
            }

            const openUrl = openNouveauReglementButton.getAttribute('data-open-url');

            if (openUrl && window.location.search.includes('edit=')) {
                window.location.href = openUrl;
                return;
            }

            if (caisseConfig.shouldConfirmOnFirstEntry) {
                openFondCaisseModal('open_form');
                return;
            }

            showSaisieForm();
        });
    }

    finJourneeForms.forEach(function (finJourneeForm) {
        finJourneeForm.addEventListener('submit', function (event) {
            const submitButton = finJourneeForm.querySelector('button[type="submit"]');
            if (submitButton && submitButton.disabled) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            openFinJourneeModal(finJourneeForm);
        }, true);
    });

    finJourneeModeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }

            setFinJourneeMode(button.getAttribute('data-fin-mode-value') || 'without_deposit');
            setFinJourneeError('');
        });
    });

    [finJourneeEspecesToggle, finJourneeChequesToggle].forEach(function (checkbox) {
        if (!checkbox) {
            return;
        }

        checkbox.addEventListener('change', function () {
            toggleFinJourneeDepositFields();
            setFinJourneeError('');
        });
    });

    if (finJourneeCancelButton) {
        finJourneeCancelButton.addEventListener('click', closeFinJourneeModal);
    }

    if (finJourneeModal) {
        finJourneeModal.addEventListener('click', function (event) {
            if (event.target === finJourneeModal || event.target.closest('[data-close-ldc-modal="fin"]')) {
                closeFinJourneeModal();
            }
        });
    }

    modalCloseTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const modalName = trigger.getAttribute('data-close-ldc-modal');
            if (modalName === 'fin') {
                closeFinJourneeModal();
                return;
            }

            closeFondCaisseModal();
        });
    });

    if (finJourneeConfirmButton) {
        finJourneeConfirmButton.addEventListener('click', function () {
            if (!currentFinJourneeForm) {
                closeFinJourneeModal();
                return;
            }

            const formToSubmit = currentFinJourneeForm;
            const submitButton = formToSubmit.querySelector('button[type="submit"]');
            const depotOnInput = formToSubmit.querySelector('[name="depot_on"]');
            const depotEspeceInput = formToSubmit.querySelector('[name="depot_espece"]');
            const depotChequeInput = formToSubmit.querySelector('[name="depot_cheque"]');
            const montantRemiseEspecesInput = formToSubmit.querySelector('[name="montant_remise_especes"]');
            const montantRemiseChequeInput = formToSubmit.querySelector('[name="montant_remise_cheque"]');
            const finNumRemiseEspecesInput = formToSubmit.querySelector('[name="fin_num_remise_especes"]');
            const finNumRemiseChequeInput = formToSubmit.querySelector('[name="fin_num_remise_cheque"]');
            const visibleNumRemiseEspecesInput = document.querySelector('#caisse-remise-form [name="num_remise_especes"]');
            const visibleNumRemiseChequeInput = document.querySelector('#caisse-remise-form [name="num_remise_cheque"]');
            const totalEspeces = parseMoneyValue(caisseConfig.totalEspeces || 0);
            const totalCheques = parseMoneyValue(caisseConfig.totalCheques || 0);

            let depotOn = 0;
            let depotEspece = 0;
            let depotCheque = 0;
            let montantRemiseEspeces = '';
            let montantRemiseCheque = '';
            let numRemiseEspeces = '';
            let numRemiseCheque = '';

            if (finJourneeMode === 'with_deposit') {
                if (finJourneeEspecesToggle && finJourneeEspecesToggle.checked && totalEspeces > 0) {
                    const parsedEspeces = parseMoneyValue(finJourneeEspecesAmount ? finJourneeEspecesAmount.value : 0);
                    if (!(parsedEspeces > 0)) {
                        setFinJourneeError('Le montant du d\u00e9p\u00f4t d\u2019esp\u00e8ces doit \u00eatre sup\u00e9rieur \u00e0 0.');
                        if (finJourneeEspecesAmount) {
                            finJourneeEspecesAmount.focus();
                        }
                        return;
                    }

                    numRemiseEspeces = finJourneeEspecesRemise ? finJourneeEspecesRemise.value.trim() : '';
                    if (!numRemiseEspeces) {
                        setFinJourneeError('Le num\u00e9ro de remise esp\u00e8ces est obligatoire pour cl\u00f4turer avec d\u00e9p\u00f4t esp\u00e8ces.');
                        if (finJourneeEspecesRemise) {
                            finJourneeEspecesRemise.focus();
                        }
                        return;
                    }

                    depotOn = 1;
                    depotEspece = 1;
                    montantRemiseEspeces = parsedEspeces.toFixed(2);
                }

                if (finJourneeChequesToggle && finJourneeChequesToggle.checked && totalCheques > 0) {
                    numRemiseCheque = finJourneeChequesRemise ? finJourneeChequesRemise.value.trim() : '';
                    if (!numRemiseCheque) {
                        setFinJourneeError('Le num\u00e9ro de remise ch\u00e8que est obligatoire pour cl\u00f4turer avec d\u00e9p\u00f4t ch\u00e8que.');
                        if (finJourneeChequesRemise) {
                            finJourneeChequesRemise.focus();
                        }
                        return;
                    }

                    depotOn = 1;
                    depotCheque = 1;
                    montantRemiseCheque = totalCheques.toFixed(2);
                }

                if (!depotOn) {
                    setFinJourneeError('S\u00e9lectionnez au moins un d\u00e9p\u00f4t ou choisissez la cl\u00f4ture sans d\u00e9p\u00f4t.');
                    return;
                }
            }

            if (depotOnInput) {
                depotOnInput.value = String(depotOn);
            }
            if (depotEspeceInput) {
                depotEspeceInput.value = String(depotEspece);
            }
            if (depotChequeInput) {
                depotChequeInput.value = String(depotCheque);
            }
            if (montantRemiseEspecesInput) {
                montantRemiseEspecesInput.value = montantRemiseEspeces;
            }
            if (montantRemiseChequeInput) {
                montantRemiseChequeInput.value = montantRemiseCheque;
            }
            if (finNumRemiseEspecesInput) {
                finNumRemiseEspecesInput.value = numRemiseEspeces;
            }
            if (finNumRemiseChequeInput) {
                finNumRemiseChequeInput.value = numRemiseCheque;
            }

            if (visibleNumRemiseEspecesInput && numRemiseEspeces !== '') {
                visibleNumRemiseEspecesInput.value = numRemiseEspeces;
                caisseConfig.numRemiseEspeces = numRemiseEspeces;
            }
            if (visibleNumRemiseChequeInput && numRemiseCheque !== '') {
                visibleNumRemiseChequeInput.value = numRemiseCheque;
                caisseConfig.numRemiseCheque = numRemiseCheque;
            }

            finJourneeConfirmButton.disabled = true;
            closeFinJourneeModal();

            if (submitButton) {
                submitButton.disabled = true;
            }

            formToSubmit.submit();
        });
    }

    printButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const filter = button.getAttribute('data-print-filter') || 'all';
            if (filter === 'all') {
                clearPrintFilter();
            } else {
                document.documentElement.setAttribute('data-ldc-print-filter', filter);
            }

            window.setTimeout(function () {
                window.print();
            }, 20);
        });
    });

    window.addEventListener('afterprint', clearPrintFilter);

    document.addEventListener('click', function (event) {
        monthPickers.forEach(function (picker) {
            if (!picker.contains(event.target)) {
                closeMonthPicker(picker);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllMonthPickers();
            if (finJourneeModal && !finJourneeModal.classList.contains('is-hidden')) {
                closeFinJourneeModal();
                return;
            }

            if (fondCaisseModal && !fondCaisseModal.classList.contains('is-hidden')) {
                closeFondCaisseModal();
            }
        }

        if (event.key === 'Enter' && !fondCaisseModal?.classList.contains('is-hidden') && event.target === fondCaisseModalInput) {
            event.preventDefault();
            confirmFondCaisse();
        }
    });

    if (form) {
        syncFondCaisseFields(caisseConfig.fondCaisseDebut || 0);
        refreshSequentialSelectors();

        form.addEventListener('reset', function () {
            window.setTimeout(function () {
                selectedAttachmentFiles = [];
                refreshTypeEncaissementOptions();
                refreshSequentialSelectors();
                toggleCheque();
                toggleTypeAffaireFields();
                syncAllMonthPickers();
                closeAllMonthPickers();
                syncFondCaisseFields(caisseConfig.fondCaisseDebut || 0);
                syncAttachmentInputFiles();
                renderSelectedAttachments();
                if (fondCaisseConfirmedInput) {
                    fondCaisseConfirmedInput.value = caisseConfig.shouldConfirmOnFirstEntry ? '0' : '1';
                }
            }, 0);
        });
    }

    if (tableToggleButton && tableScroll) {
        tableToggleButton.addEventListener('click', function () {
            const isExpanded = tableScroll.classList.toggle('is-expanded');
            if (tablePanel) {
                tablePanel.classList.toggle('is-expanded', isExpanded);
            }
            tableToggleButton.textContent = isExpanded ? 'Reduire le tableau' : 'Afficher toutes les colonnes';
            tableToggleButton.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }

    document.querySelectorAll('.form-delete').forEach(function (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            const confirmMessage = deleteForm.getAttribute('data-confirm-message') || 'Voulez-vous vraiment supprimer cet element ?';
            if (!window.confirm(confirmMessage)) {
                event.preventDefault();
            }
        });
    });

    if (window.location.search.includes('edit=')) {
        showSaisieForm();
    }

    renderSelectedAttachments();
});
