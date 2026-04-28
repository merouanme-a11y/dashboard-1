document.addEventListener("DOMContentLoaded", function () {
    const cfg = window.__BI_CONFIG__ || {};
    const dom = {
        connectionSelect: document.getElementById("biConnectionSelect"),
        connectionField: document.getElementById("biConnectionField"),
        fileSelect: document.getElementById("biFileSelect"),
        fileField: document.getElementById("biFileField"),
        pageSelect: document.getElementById("biPageSelect"),
        addPageBtn: document.getElementById("biAddPageBtn"),
        duplicatePageBtn: document.getElementById("biDuplicatePageBtn"),
        deletePageBtn: document.getElementById("biDeletePageBtn"),
        settingsBtn: document.getElementById("biSettingsBtn"),
        editModeBtn: document.getElementById("biEditModeBtn"),
        refreshBtn: document.getElementById("biRefreshBtn"),
        saveStatus: document.getElementById("biSaveStatus"),
        builderTopbar: document.getElementById("biBuilderTopbar"),
        widgetPalette: document.getElementById("biWidgetPalette"),
        addFilterBtn: document.getElementById("biAddFilterBtn"),
        loading: document.getElementById("biLoading"),
        shell: document.getElementById("biShell"),
        dataFeedback: document.getElementById("biDataFeedback"),
        widgetsGrid: document.getElementById("biWidgetsGrid"),
        emptyState: document.getElementById("biEmptyState"),
        filtersBar: document.getElementById("biFiltersBar"),
        widgetModal: document.getElementById("biWidgetModal"),
        widgetModalClose: document.getElementById("biWidgetModalClose"),
        inspectorHint: document.getElementById("biInspectorHint"),
        inspectorEmpty: document.getElementById("biInspectorEmpty"),
        inspectorForm: document.getElementById("biInspectorForm"),
        widgetDataDescription: document.getElementById("biWidgetDataDescription"),
        widgetDataHint: document.getElementById("biWidgetDataHint"),
        widgetDataBuilder: document.getElementById("biWidgetDataBuilder"),
        widgetPreview: document.getElementById("biWidgetPreview"),
        widgetTitle: document.getElementById("biWidgetTitle"),
        widgetType: document.getElementById("biWidgetType"),
        widgetLayout: document.getElementById("biWidgetLayout"),
        widgetAlignment: document.getElementById("biWidgetAlignment"),
        widgetCardHeight: document.getElementById("biWidgetCardHeight"),
        widgetCardHeightValue: document.getElementById("biWidgetCardHeightValue"),
        widgetTextSize: document.getElementById("biWidgetTextSize"),
        widgetTextSizeValue: document.getElementById("biWidgetTextSizeValue"),
        widgetTextSizeNumber: document.getElementById("biWidgetTextSizeNumber"),
        widgetValueSize: document.getElementById("biWidgetValueSize"),
        widgetValueSizeValue: document.getElementById("biWidgetValueSizeValue"),
        widgetValueSizeNumber: document.getElementById("biWidgetValueSizeNumber"),
        widgetChartColor: document.getElementById("biWidgetChartColor"),
        widgetChartColorInput: document.getElementById("biWidgetChartColorInput"),
        widgetChartColorHex: document.getElementById("biWidgetChartColorHex"),
        widgetBgColor: document.getElementById("biWidgetBgColor"),
        widgetBgColorInput: document.getElementById("biWidgetBgColorInput"),
        widgetBgColorHex: document.getElementById("biWidgetBgColorHex"),
        widgetTextColor: document.getElementById("biWidgetTextColor"),
        widgetTextColorInput: document.getElementById("biWidgetTextColorInput"),
        widgetTextColorHex: document.getElementById("biWidgetTextColorHex"),
        widgetTitleColor: document.getElementById("biWidgetTitleColor"),
        widgetTitleColorInput: document.getElementById("biWidgetTitleColorInput"),
        widgetTitleColorHex: document.getElementById("biWidgetTitleColorHex"),
        widgetValueColor: document.getElementById("biWidgetValueColor"),
        widgetValueColorInput: document.getElementById("biWidgetValueColorInput"),
        widgetValueColorHex: document.getElementById("biWidgetValueColorHex"),
        widgetHideTitle: document.getElementById("biWidgetHideTitle"),
        widgetHideText: document.getElementById("biWidgetHideText"),
        settingsModal: document.getElementById("biSettingsModal"),
        settingsModalClose: document.getElementById("biSettingsModalClose"),
        settingsFeedback: document.getElementById("biSettingsFeedback"),
        settingsTabs: document.querySelectorAll("[data-settings-tab]"),
        settingsPanels: document.querySelectorAll("[data-settings-panel]"),
        uploadSourceForm: document.getElementById("biUploadSourceForm"),
        uploadSourceLabel: document.getElementById("biUploadSourceLabel"),
        uploadSourceFile: document.getElementById("biUploadSourceFile"),
        remoteSourceForm: document.getElementById("biRemoteSourceForm"),
        remoteSourceLabel: document.getElementById("biRemoteSourceLabel"),
        remoteSourceUrl: document.getElementById("biRemoteSourceUrl"),
        apiSourceForm: document.getElementById("biApiSourceForm"),
        apiSourceLabel: document.getElementById("biApiSourceLabel"),
        apiSourceUrl: document.getElementById("biApiSourceUrl"),
        apiSourceToken: document.getElementById("biApiSourceToken"),
        editSourceCard: document.getElementById("biEditSourceCard"),
        editSourceForm: document.getElementById("biEditSourceForm"),
        editSourceId: document.getElementById("biEditSourceId"),
        editSourceTitle: document.getElementById("biEditSourceTitle"),
        editSourceDescription: document.getElementById("biEditSourceDescription"),
        editSourceLabel: document.getElementById("biEditSourceLabel"),
        editSourceUrlField: document.getElementById("biEditSourceUrlField"),
        editSourceUrl: document.getElementById("biEditSourceUrl"),
        editSourceUrlHelp: document.getElementById("biEditSourceUrlHelp"),
        editSourceTokenField: document.getElementById("biEditSourceTokenField"),
        editSourceToken: document.getElementById("biEditSourceToken"),
        editSourceTokenHelp: document.getElementById("biEditSourceTokenHelp"),
        editSourceInfoField: document.getElementById("biEditSourceInfoField"),
        editSourceInfo: document.getElementById("biEditSourceInfo"),
        editSourceSubmit: document.getElementById("biEditSourceSubmit"),
        editSourceCancel: document.getElementById("biEditSourceCancel"),
        settingsSourcesList: document.getElementById("biSettingsSourcesList"),
        creationPermissionsForm: document.getElementById("biCreationPermissionsForm"),
        creationPermissionsUsers: document.getElementById("biCreationPermissionsUsers"),
        creationPermissionsProfiles: document.getElementById("biCreationPermissionsProfiles"),
        pagePermissionsCard: document.getElementById("biPagePermissionsCard"),
        pagePermissionsDescription: document.getElementById("biPagePermissionsDescription"),
        pagePermissionsForm: document.getElementById("biPagePermissionsForm"),
        pagePermissionsUsers: document.getElementById("biPagePermissionsUsers"),
        pagePermissionsProfiles: document.getElementById("biPagePermissionsProfiles"),
    };
    const palette = ["#2563eb", "#1d4ed8", "#0f766e", "#10b981", "#84cc16", "#eab308", "#f59e0b", "#f97316", "#ef4444", "#dc2626", "#ec4899", "#be185d", "#8b5cf6", "#7c3aed", "#06b6d4", "#334155"];
    const fractions = ["1/8", "2/8", "3/8", "4/8", "5/8", "6/8", "7/8", "8/8"];
    const transparentDragImage = createTransparentDragImage();
    const WIDGET_TEXT_SIZE_MIN = 0;
    const WIDGET_TEXT_SIZE_MAX = 150;
    const WIDGET_TEXT_SIZE_DEFAULT = 15;
    const WIDGET_VALUE_SIZE_MIN = 0;
    const WIDGET_VALUE_SIZE_MAX = 150;
    const WIDGET_VALUE_SIZE_DEFAULT = 48;
    const datasetBrowserCacheTtlMs = 5 * 60 * 1000;
    const datasetBrowserCacheMaxBytes = 1500000;
    const defaultWidgetCatalog = [
        { type: "kpi", label: "Indicateur KPI", icon: "bi-speedometer2", defaultTitle: "KPI principal" },
        { type: "counter", label: "Compteur", icon: "bi-123", defaultTitle: "Compteur global" },
        { type: "percentage", label: "Carte de pourcentage", icon: "bi-percent", defaultTitle: "Part en pourcentage" },
        { type: "bar", label: "Graphique en barres", icon: "bi-bar-chart-line", defaultTitle: "Repartition par categorie" },
        { type: "bar-horizontal", label: "Barres horizontales", icon: "bi-bar-chart-steps", defaultTitle: "Classement horizontal" },
        { type: "line", label: "Courbe d evolution", icon: "bi-graph-up", defaultTitle: "Evolution dans le temps" },
        { type: "pie", label: "Graphique en secteurs", icon: "bi-pie-chart", defaultTitle: "Part par categorie" },
        { type: "doughnut", label: "Camembert annulaire", icon: "bi-circle", defaultTitle: "Distribution annulaire" },
        { type: "histogram", label: "Histogramme", icon: "bi-distribute-vertical", defaultTitle: "Distribution numerique" },
        { type: "distribution-table", label: "Tableau de repartition", icon: "bi-list-columns-reverse", defaultTitle: "Repartition detaillee" },
        { type: "table", label: "Tableau de donnees", icon: "bi-table", defaultTitle: "Tableau detaille" },
        { type: "datatable", label: "Tableau personnalise", icon: "bi-grid-3x3-gap", defaultTitle: "Tableau personnalise" },
    ];
    const state = {
        connections: Array.isArray(cfg.preloadedConnections) ? cfg.preloadedConnections : [],
        files: Array.isArray(cfg.preloadedFiles) ? cfg.preloadedFiles : [],
        dataset: cfg.preloadedDataset && typeof cfg.preloadedDataset === "object" ? cfg.preloadedDataset : null,
        builderOptions: buildBuilderOptionsFromDataset(cfg.preloadedDataset),
        widgetCatalog: mergeWidgetCatalog(cfg.builderOptions?.widgets),
        preferences: normalizePreferences(cfg.preferences),
        moduleSettings: normalizeModuleSettings(cfg.moduleSettings),
        rightsDirectory: normalizeRightsDirectory(cfg.rightsDirectory),
        microsoftAuthConfigured: Boolean(cfg.microsoftAuth?.configured),
        selectedPageId: "",
        selectedWidgetId: "",
        canCreatePages: Boolean(cfg.access?.canCreatePages),
        canManageSettings: Boolean(cfg.access?.canManageSettings),
        editMode: false,
        charts: {},
        previewChart: null,
        draggingCard: null,
        insertBeforeEl: null,
        dndIndicator: null,
        dndRaf: 0,
        saveTimer: 0,
        saveInFlight: false,
        saveQueued: false,
        preferencesRevision: 0,
        saveStatusTimer: 0,
        activeColorPopover: null,
        dataFeedbackMessage: String(cfg.preloadedDataset?._error || ""),
        dataFeedbackType: cfg.preloadedDataset?._error ? "error" : "",
        editingModuleSourceId: "",
        activeSettingsTab: "connections",
    };

    state.canCreatePages = Boolean(state.preferences.canCreatePages || state.canCreatePages);
    state.canManageSettings = Boolean(state.preferences.canManageSettings || state.canManageSettings);
    state.selectedPageId = String(state.preferences.selectedPageId || state.preferences.pages[0]?.id || "page-bi-1");

    bindEvents();
    initialize();

    function initialize() {
        renderConnections();
        renderPages();
        renderPalette();
        syncToolbarWithPage();

        if (state.dataset && !state.dataset._error) {
            state.builderOptions = buildBuilderOptionsFromDataset(state.dataset);
            setLoading(false);
            renderAll();
            return;
        }

        const page = getCurrentPage();
        if (!page) {
            setLoading(false);
            renderAll();
            return;
        }

        if (page.connectionId) {
            loadFiles(page.connectionId, page.fileId || "", Boolean(cfg.preloadedFiles?.length));
        } else if (cfg.preloadedConnectionId) {
            page.connectionId = String(cfg.preloadedConnectionId);
            page.fileId = String(cfg.preloadedFileId || "");
            loadFiles(page.connectionId, page.fileId, Boolean(cfg.preloadedFiles?.length));
        } else {
            setLoading(false);
            renderAll();
        }

        if (cfg.openSettingsOnLoad && dom.settingsModal) {
            renderSettingsModal();
            if (cfg.preloadedSettingsFeedback?.message) {
                showSettingsFeedback(String(cfg.preloadedSettingsFeedback.message || ""), String(cfg.preloadedSettingsFeedback.type || ""));
            }
            openModal("settings");
        }
    }

    function bindEvents() {
        dom.connectionSelect?.addEventListener("change", function () {
            const page = getCurrentPage();
            if (!page || !canEditCurrentPage() || !state.editMode) return;
            page.connectionId = String(dom.connectionSelect.value || "");
            page.fileId = "";
            state.dataset = null;
            state.builderOptions = buildBuilderOptionsFromDataset(null);
            state.selectedWidgetId = "";
            loadFiles(page.connectionId, "", false);
            scheduleSavePreferences();
        });

        dom.fileSelect?.addEventListener("change", function () {
            const page = getCurrentPage();
            if (!page || !canEditCurrentPage() || !state.editMode) return;
            page.fileId = String(dom.fileSelect.value || "");
            loadDataset(page.connectionId, page.fileId, false);
            scheduleSavePreferences();
        });

        dom.pageSelect?.addEventListener("change", function () {
            state.selectedPageId = String(dom.pageSelect.value || "");
            state.preferences.selectedPageId = state.selectedPageId;
            state.selectedWidgetId = "";
            syncToolbarWithPage();
            renderAll();
            scheduleSavePreferences();
        });

        dom.addPageBtn?.addEventListener("click", function () {
            if (!state.canCreatePages) return;
            const name = window.prompt("Nom de la nouvelle page BI", "Nouvelle page BI");
            if (!name) return;
            const current = getCurrentPage();
            const nextPage = {
                id: createId("page"),
                name: String(name).trim() || "Nouvelle page BI",
                connectionId: current?.connectionId || state.connections[0]?.id || "",
                fileId: current?.fileId || "",
                filters: [],
                widgets: [],
                ownerUserId: 0,
                ownerEmail: "",
                ownerDisplayName: "",
                allowedUserIds: [],
                allowedProfileTypes: [],
                canEdit: true,
                canManagePermissions: true,
            };
            state.preferences.pages.push(nextPage);
            state.selectedPageId = nextPage.id;
            state.preferences.selectedPageId = nextPage.id;
            state.selectedWidgetId = "";
            syncToolbarWithPage();
            renderAll();
            scheduleSavePreferences();
        });

        dom.duplicatePageBtn?.addEventListener("click", function () {
            if (!state.canCreatePages) return;
            const page = getCurrentPage();
            if (!page) return;
            const duplicate = deepClone(page);
            duplicate.id = createId("page");
            duplicate.name = page.name + " copie";
            duplicate.widgets = duplicate.widgets.map(function (widget) {
                widget.id = createId("widget");
                return widget;
            });
            duplicate.ownerUserId = 0;
            duplicate.ownerEmail = "";
            duplicate.ownerDisplayName = "";
            duplicate.canEdit = true;
            duplicate.canManagePermissions = true;
            state.preferences.pages.push(duplicate);
            state.selectedPageId = duplicate.id;
            state.preferences.selectedPageId = duplicate.id;
            state.selectedWidgetId = "";
            syncToolbarWithPage();
            renderAll();
            scheduleSavePreferences();
        });

        dom.deletePageBtn?.addEventListener("click", function () {
            if (!canEditCurrentPage()) return;
            if (state.preferences.pages.length <= 1) return;
            const page = getCurrentPage();
            if (!page || !window.confirm("Supprimer cette page BI ?")) return;
            state.preferences.pages = state.preferences.pages.filter(function (candidate) {
                return candidate.id !== page.id;
            });
            state.selectedPageId = state.preferences.pages[0]?.id || "";
            state.preferences.selectedPageId = state.selectedPageId;
            state.selectedWidgetId = "";
            syncToolbarWithPage();
            renderAll();
            scheduleSavePreferences();
        });

        dom.settingsBtn?.addEventListener("click", function () {
            if (!(state.canManageSettings || canManageCurrentPagePermissions() || state.microsoftAuthConfigured)) return;
            clearSettingsFeedback();
            resetEditSourceForm();
            renderSettingsModal();
            openModal("settings");
        });

        dom.editModeBtn?.addEventListener("click", function () {
            if (!canEditCurrentPage()) return;
            state.editMode = !state.editMode;
            if (!state.editMode) {
                state.selectedWidgetId = "";
                closeModal("widget");
                flushPendingSavePreferences();
            }
            dom.editModeBtn.innerHTML = state.editMode ? '<i class="bi bi-check2-square"></i>' : '<i class="bi bi-pencil-square"></i>';
            renderAll();
        });

        dom.refreshBtn?.addEventListener("click", function () {
            const page = getCurrentPage();
            if (!page?.connectionId || !page.fileId) return;
            loadFiles(page.connectionId, page.fileId, false, true);
        });

        dom.addFilterBtn?.addEventListener("click", function () {
            const page = getCurrentPage();
            const columns = getColumnOptions();
            if (!page || !columns.length || !canEditCurrentPage()) return;
            const firstColumn = columns.find(function (column) { return column.type === "string" || column.type === "boolean"; }) || columns[0];
            const values = getDistinctColumnValues(firstColumn.key);
            page.filters.push({ column: firstColumn.key, value: values[0] || "" });
            renderAll();
            scheduleSavePreferences();
        });

        dom.widgetsGrid?.addEventListener("click", function (event) {
            if (!canEditCurrentPage() || !state.editMode) return;
            if (event.target === dom.widgetsGrid) {
                state.selectedWidgetId = "";
                syncSelectedWidgetCardState();
                closeModal("widget");
                renderInspector();
            }
        });

        dom.widgetsGrid?.addEventListener("dragenter", function (event) {
            if (canEditCurrentPage() && state.editMode && state.draggingCard) {
                event.preventDefault();
            }
        });

        dom.widgetsGrid?.addEventListener("dragover", function (event) {
            if (!(canEditCurrentPage() && state.editMode) || !state.draggingCard) return;
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = "move";
            }
            if (state.dndRaf) return;
            state.dndRaf = window.requestAnimationFrame(function () {
                state.dndRaf = 0;
                dndComputeAndShow(dom.widgetsGrid, event.clientX, event.clientY);
            });
        });

        dom.widgetsGrid?.addEventListener("dragleave", function (event) {
            if (!state.draggingCard) return;
            const related = event.relatedTarget;
            if (related && dom.widgetsGrid.contains(related)) return;
            dndHideIndicator();
        });

        dom.widgetsGrid?.addEventListener("drop", function (event) {
            if (!(canEditCurrentPage() && state.editMode) || !state.draggingCard) return;
            event.preventDefault();
            reorderWidgetsFromDrop();
            cleanupDraggingState();
            renderAll();
            scheduleSavePreferences();
        });

        dom.widgetModalClose?.addEventListener("click", function () {
            closeModal("widget");
        });

        dom.settingsModalClose?.addEventListener("click", function () {
            closeModal("settings");
        });

        document.querySelectorAll("[data-modal-close]").forEach(function (element) {
            element.addEventListener("click", function () {
                const modalName = String(element.getAttribute("data-modal-close") || "");
                closeModal(modalName);
            });
        });

        dom.settingsTabs?.forEach(function (button) {
            button.addEventListener("click", function () {
                setActiveSettingsTab(String(button.getAttribute("data-settings-tab") || "connections"));
            });
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeColorPopover();
                if (!dom.widgetModal?.hidden) {
                    closeModal("widget");
                } else if (!dom.settingsModal?.hidden) {
                    closeModal("settings");
                }
            }
        });

        document.addEventListener("click", function (event) {
            if (!(event.target instanceof Element)) return;
            if (state.activeColorPopover?.popover?.contains(event.target)) {
                return;
            }
            if (!event.target.closest(".color-input-wrapper, .bi-modal-color-field")) {
                closeColorPopover();
            }
        });

        window.addEventListener("resize", function () {
            if (state.activeColorPopover?.reposition) {
                state.activeColorPopover.reposition();
            }
        });

        window.addEventListener("scroll", function () {
            if (state.activeColorPopover?.reposition) {
                state.activeColorPopover.reposition();
            }
        }, true);

        dom.uploadSourceForm?.addEventListener("submit", handleUploadSourceSubmit);
        dom.remoteSourceForm?.addEventListener("submit", handleRemoteSourceSubmit);
        dom.apiSourceForm?.addEventListener("submit", handleApiSourceSubmit);
        dom.editSourceForm?.addEventListener("submit", handleEditSourceSubmit);
        dom.editSourceCancel?.addEventListener("click", function () {
            resetEditSourceForm();
        });
        dom.creationPermissionsForm?.addEventListener("submit", handleCreationPermissionsSubmit);
        dom.pagePermissionsForm?.addEventListener("submit", handlePagePermissionsSubmit);

        bindInspectorEvents();
    }

    function bindInspectorEvents() {
        bindInlineColorField(dom.widgetChartColor, dom.widgetChartColorInput, dom.widgetChartColorHex, "color", "#3b82f6");
        bindInlineColorField(dom.widgetBgColor, dom.widgetBgColorInput, dom.widgetBgColorHex, "bgColor", "#1f2937");
        bindInlineColorField(dom.widgetTextColor, dom.widgetTextColorInput, dom.widgetTextColorHex, "textColor", "#f8fafc");
        bindInlineColorField(dom.widgetTitleColor, dom.widgetTitleColorInput, dom.widgetTitleColorHex, "titleColor", "#f8fafc");
        bindInlineColorField(dom.widgetValueColor, dom.widgetValueColorInput, dom.widgetValueColorHex, "valueColor", "#60a5fa");

        dom.widgetTitle?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.title = String(dom.widgetTitle.value || "");
            refreshWidgetAfterModalEdit();
        });

        dom.widgetType?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.type = String(dom.widgetType.value || "bar");
            applyWidgetDefaults(widget, true);
            refreshWidgetAfterModalEdit({ rebuildInspector: true });
        });

        dom.widgetLayout?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.layout = String(dom.widgetLayout.value || "4/8");
            refreshWidgetAfterModalEdit();
        });

        dom.widgetAlignment?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.alignment = String(dom.widgetAlignment.value || "left");
            refreshWidgetAfterModalEdit();
        });

        dom.widgetCardHeight?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.cardHeight = clamp(parseInt(dom.widgetCardHeight.value, 10) || 75, 75, 520);
            if (dom.widgetCardHeightValue) {
                dom.widgetCardHeightValue.textContent = String(widget.cardHeight) + " px";
            }
            refreshWidgetAfterModalEdit();
        });

        dom.widgetTextSize?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.textSize = normalizeWidgetTextSize(dom.widgetTextSize.value);
            syncWidgetTextSizeControls(widget.textSize);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetTextSizeNumber?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;

            const parsedValue = parseOptionalInteger(dom.widgetTextSizeNumber.value);
            if (parsedValue === null) {
                return;
            }

            widget.textSize = normalizeWidgetTextSize(parsedValue);
            syncWidgetTextSizeControls(widget.textSize);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetTextSizeNumber?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;

            widget.textSize = normalizeWidgetTextSize(dom.widgetTextSizeNumber.value);
            syncWidgetTextSizeControls(widget.textSize);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetValueSize?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.valueSize = normalizeWidgetValueSize(dom.widgetValueSize.value);
            syncWidgetValueSizeControls(widget.valueSize);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetValueSizeNumber?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;

            const parsedValue = parseOptionalInteger(dom.widgetValueSizeNumber.value);
            if (parsedValue === null) {
                return;
            }

            widget.valueSize = normalizeWidgetValueSize(parsedValue);
            syncWidgetValueSizeControls(widget.valueSize);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetValueSizeNumber?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;

            widget.valueSize = normalizeWidgetValueSize(dom.widgetValueSizeNumber.value);
            syncWidgetValueSizeControls(widget.valueSize);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetHideTitle?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.hideTitle = Boolean(dom.widgetHideTitle.checked);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetHideText?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.hideText = Boolean(dom.widgetHideText.checked);
            refreshWidgetAfterModalEdit();
        });

    }

    function renderAll() {
        closeColorPopover();
        const pageCanEdit = canEditCurrentPage();
        dom.shell?.classList.toggle("is-edit-mode", pageCanEdit && state.editMode);
        if (!pageCanEdit && state.editMode) {
            state.editMode = false;
            if (dom.editModeBtn) {
                dom.editModeBtn.innerHTML = '<i class="bi bi-pencil-square"></i>';
            }
        }
        const showSourceSelectors = pageCanEdit && state.editMode;
        if (dom.connectionField) dom.connectionField.hidden = !showSourceSelectors;
        if (dom.fileField) dom.fileField.hidden = !showSourceSelectors;
        if (dom.connectionSelect) dom.connectionSelect.disabled = !showSourceSelectors;
        if (dom.fileSelect) dom.fileSelect.disabled = !showSourceSelectors;
        if (dom.addPageBtn) dom.addPageBtn.hidden = !(state.canCreatePages && state.editMode);
        if (dom.duplicatePageBtn) dom.duplicatePageBtn.hidden = !(state.canCreatePages && state.editMode);
        if (dom.deletePageBtn) dom.deletePageBtn.hidden = !(pageCanEdit && state.editMode);
        if (dom.settingsBtn) dom.settingsBtn.hidden = !(state.canManageSettings || canManageCurrentPagePermissions());
        if (dom.editModeBtn) dom.editModeBtn.hidden = !pageCanEdit;
        renderConnections();
        renderFiles();
        renderPages();
        const hydratedWidgets = hydrateCurrentPageWidgetsForDataset();
        renderPalette();
        renderFiltersBar();
        renderWidgets();
        renderInspector();
        renderDataFeedback();
        setLoading(false);
        if (hydratedWidgets) {
            scheduleSavePreferences();
        }
    }

    function renderConnections() {
        if (!dom.connectionSelect) return;
        const current = getCurrentPage();
        const options = ['<option value="">Selectionner une connexion</option>'];
        state.connections.forEach(function (connection) {
            const selected = current?.connectionId === connection.id ? " selected" : "";
            options.push('<option value="' + escapeHtml(connection.id) + '"' + selected + ">" + escapeHtml(connection.label) + "</option>");
        });
        dom.connectionSelect.innerHTML = options.join("");
        dom.connectionSelect.disabled = !(canEditCurrentPage() && state.editMode);
    }

    function renderFiles() {
        if (!dom.fileSelect) return;
        const current = getCurrentPage();
        const options = ['<option value="">Selectionner un fichier</option>'];
        state.files.forEach(function (file) {
            const selected = current?.fileId === file.id ? " selected" : "";
            options.push('<option value="' + escapeHtml(file.id) + '"' + selected + ">" + escapeHtml(file.name) + " (" + escapeHtml(file.extension.toUpperCase()) + ")</option>");
        });
        dom.fileSelect.innerHTML = options.join("");
        dom.fileSelect.disabled = !(canEditCurrentPage() && state.editMode);
    }

    function renderPages() {
        if (!dom.pageSelect) return;
        dom.pageSelect.innerHTML = state.preferences.pages.map(function (page) {
            const selected = page.id === state.selectedPageId ? " selected" : "";
            const suffix = page.ownerDisplayName ? " - " + page.ownerDisplayName : "";
            return '<option value="' + escapeHtml(page.id) + '"' + selected + ">" + escapeHtml(page.name + suffix) + "</option>";
        }).join("");

        if (dom.deletePageBtn) {
            dom.deletePageBtn.disabled = state.preferences.pages.length <= 1 || !canEditCurrentPage();
        }
    }

    function openModal(name) {
        if (name === "widget" && dom.widgetModal) {
            dom.widgetModal.hidden = false;
        }
        if (name === "settings" && dom.settingsModal) {
            dom.settingsModal.hidden = false;
        }
        syncModalBodyState();
    }

    function closeModal(name) {
        if (name === "widget" && dom.widgetModal) {
            dom.widgetModal.hidden = true;
            destroyPreviewChart();
        }
        if (name === "settings" && dom.settingsModal) {
            dom.settingsModal.hidden = true;
            clearSettingsFeedback();
            resetEditSourceForm();
        }
        closeColorPopover();
        syncModalBodyState();
    }

    function isWidgetModalOpen() {
        return Boolean(dom.widgetModal && dom.widgetModal.hidden === false);
    }

    function syncModalBodyState() {
        document.body.classList.toggle(
            "bi-modal-open",
            Boolean(
                (dom.widgetModal && !dom.widgetModal.hidden)
                || (dom.settingsModal && !dom.settingsModal.hidden)
            )
        );
    }

    function captureWidgetModalState() {
        const dialog = dom.widgetModal?.querySelector(".bi-widget-modal-dialog");
        const inspectorMain = dom.inspectorForm?.querySelector(".bi-inspector-main");

        return {
            dialogScrollTop: dialog ? dialog.scrollTop : 0,
            inspectorMainScrollTop: inspectorMain ? inspectorMain.scrollTop : 0,
        };
    }

    function restoreWidgetModalState(snapshot) {
        if (!snapshot) return;

        const dialog = dom.widgetModal?.querySelector(".bi-widget-modal-dialog");
        const inspectorMain = dom.inspectorForm?.querySelector(".bi-inspector-main");

        if (dialog) {
            dialog.scrollTop = Number(snapshot.dialogScrollTop || 0);
        }

        if (inspectorMain) {
            inspectorMain.scrollTop = Number(snapshot.inspectorMainScrollTop || 0);
        }
    }

    function syncSelectedWidgetCardState() {
        if (!dom.widgetsGrid) {
            return;
        }

        dom.widgetsGrid.querySelectorAll(".card[data-widget-id]").forEach(function (card) {
            card.classList.toggle("bi-widget-selected", String(card.getAttribute("data-widget-id") || "") === String(state.selectedWidgetId || ""));
        });
    }

    function refreshWidgetAfterModalEdit(options) {
        const widget = getSelectedWidget();
        if (!widget) return;

        if (isWidgetModalOpen()) {
            const modalState = captureWidgetModalState();
            if (options?.rebuildInspector) {
                renderInspector();
                restoreWidgetModalState(modalState);
            } else {
                updateInspectorPreview(widget);
                restoreWidgetModalState(modalState);
            }
            refreshRenderedWidgetCard(widget);
        } else if (options?.full) {
            renderAll();
        } else {
            renderWidgets();
        }

        scheduleSavePreferences();
    }

    function getWidgetDataDescription(widget) {
        const type = String(widget?.type || "bar");

        if (type === "counter") {
            return "Choisissez simplement le calcul, la colonne et, si besoin, une valeur precise a compter.";
        }

        if (type === "kpi") {
            return "Selectionnez la valeur principale du KPI.";
        }

        if (type === "percentage") {
            return "Choisissez une categorie et, si besoin, une mesure ou une valeur precise a compter.";
        }

        if (type === "table") {
            return "Choisissez une ligne et une valeur simple a afficher dans le tableau.";
        }

        if (type === "datatable") {
            return "Choisissez les colonnes a afficher, ajoutez des filtres par valeur et personnalisez les couleurs du tableau.";
        }

        if (type === "distribution-table") {
            return "Choisissez une colonne pour afficher une repartition simple avec nombre, pourcentage et jauge coloree.";
        }

        if (type === "histogram") {
            return "Choisissez une colonne numerique et le nombre de classes a afficher.";
        }

        return "Choisissez un axe X et une valeur simple a calculer, avec une valeur precise a compter si besoin.";
    }

    function getDimensionColumns(columns) {
        const safeColumns = Array.isArray(columns) ? columns : [];
        const preferred = safeColumns.filter(function (column) {
            return ["string", "boolean", "date"].indexOf(String(column.type || "")) !== -1;
        });

        return preferred.length ? preferred : safeColumns;
    }

    function getNumericColumns(columns) {
        const safeColumns = Array.isArray(columns) ? columns : [];
        const preferred = safeColumns.filter(function (column) {
            return String(column.type || "") === "number";
        });

        return preferred.length ? preferred : safeColumns;
    }

    function getMeasureColumnOptions(columns, aggregation, placeholder) {
        const mode = String(aggregation || "count");
        const columnChoices = mode === "count"
            ? (Array.isArray(columns) ? columns : [])
            : getNumericColumns(columns);
        const optionLabel = placeholder || (
            mode === "count"
                ? "Toutes les lignes"
                : (mode === "percentage"
                    ? "Toutes les lignes ou colonne numerique"
                    : "Selectionner une colonne numerique")
        );

        return [{ value: "", label: optionLabel }].concat(columnChoices.map(function (column) {
            return { value: column.key, label: column.label };
        }));
    }

    function createMeasureEntry(entry) {
        return {
            id: String(entry?.id || createId("measure")),
            column: String(entry?.column || ""),
            aggregation: String(entry?.aggregation || "count"),
            matchValue: String(entry?.matchValue || ""),
        };
    }

    function createFilterEntry(entry) {
        const inputMode = ["select", "input"].indexOf(String(entry?.inputMode || "")) !== -1 ? String(entry.inputMode) : "select";
        const normalizedValues = normalizeFilterSelectionValues(entry?.values, entry?.value);
        return {
            id: String(entry?.id || createId("filter")),
            column: String(entry?.column || ""),
            operator: ["equals", "contains"].indexOf(String(entry?.operator || "")) !== -1 ? String(entry.operator) : "equals",
            inputMode: inputMode,
            value: inputMode === "input" ? String(entry?.value || "") : "",
            values: inputMode === "select" ? normalizedValues : [],
            valueStyles: inputMode === "select" ? normalizeFilterValueStyles(entry?.valueStyles, normalizedValues, entry) : [],
            styleTarget: ["none", "row", "cell"].indexOf(String(entry?.styleTarget || "")) !== -1 ? String(entry.styleTarget) : "none",
            bgColor: normalizeColorInputValue(entry?.bgColor || ""),
            textColor: normalizeColorInputValue(entry?.textColor || ""),
        };
    }

    function normalizeFilterSelectionValues(values, fallbackValue) {
        const normalized = [];
        const seen = {};
        const source = Array.isArray(values)
            ? values
            : (String(fallbackValue || "").trim() !== "" ? [fallbackValue] : []);

        source.forEach(function (value) {
            const current = String(value || "").trim();
            if (!current || seen[current]) {
                return;
            }

            seen[current] = true;
            normalized.push(current);
        });

        return normalized;
    }

    function createTableColumnStyleEntry(entry) {
        return {
            key: String(entry?.key || ""),
            bgColor: normalizeColorInputValue(entry?.bgColor || ""),
            textColor: normalizeColorInputValue(entry?.textColor || ""),
        };
    }

    function createFilterValueStyleEntry(entry) {
        return {
            value: String(entry?.value || "").trim(),
            styleTarget: ["none", "row", "cell"].indexOf(String(entry?.styleTarget || "")) !== -1 ? String(entry.styleTarget) : "none",
            bgColor: normalizeColorInputValue(entry?.bgColor || ""),
            textColor: normalizeColorInputValue(entry?.textColor || ""),
        };
    }

    function normalizeFilterValueStyles(valueStyles, allowedValues, legacyStyleSource) {
        const allowed = normalizeFilterSelectionValues(allowedValues);
        const allowedSet = {};
        allowed.forEach(function (value) {
            allowedSet[value] = true;
        });

        const normalized = [];
        const seen = {};
        (Array.isArray(valueStyles) ? valueStyles : []).forEach(function (entry) {
            const normalizedEntry = createFilterValueStyleEntry(entry);
            if (!normalizedEntry.value || !allowedSet[normalizedEntry.value] || seen[normalizedEntry.value]) {
                return;
            }

            seen[normalizedEntry.value] = true;
            normalized.push(normalizedEntry);
        });

        const legacyStyle = createFilterValueStyleEntry({
            styleTarget: legacyStyleSource?.styleTarget || "none",
            bgColor: legacyStyleSource?.bgColor || "",
            textColor: legacyStyleSource?.textColor || "",
        });

        allowed.forEach(function (value) {
            if (seen[value]) {
                return;
            }

            normalized.push(createFilterValueStyleEntry({
                value: value,
                styleTarget: legacyStyle.styleTarget,
                bgColor: legacyStyle.bgColor,
                textColor: legacyStyle.textColor,
            }));
        });

        return normalized;
    }

    function normalizeDatatableStyleConfig(style) {
        const safe = style && typeof style === "object" ? style : {};
        return {
            headerBgColor: normalizeColorInputValue(safe.headerBgColor || ""),
            headerTextColor: normalizeColorInputValue(safe.headerTextColor || ""),
            rowBgColor: normalizeColorInputValue(safe.rowBgColor || ""),
            rowAltBgColor: normalizeColorInputValue(safe.rowAltBgColor || ""),
            cellBgColor: normalizeColorInputValue(safe.cellBgColor || ""),
            cellTextColor: normalizeColorInputValue(safe.cellTextColor || ""),
        };
    }

    function defaultMeasureAggregationForWidget(widget) {
        if (widget.type === "counter" || widget.type === "table" || widget.type === "distribution-table" || widget.type === "histogram") {
            return "count";
        }

        if (widget.type === "percentage") {
            return widget.valueColumn ? "sum" : "count";
        }

        return widget.valueColumn ? "sum" : "count";
    }

    function getCompatibleMeasureAggregation(value, fallback) {
        const normalized = String(value || "");
        if (["count", "sum", "avg"].indexOf(normalized) !== -1) {
            return normalized;
        }

        return String(fallback || "count");
    }

    function ensureWidgetDataModel(widget) {
        if (!Array.isArray(widget.chartDimensions)) {
            widget.chartDimensions = widget.dimensionColumn ? [String(widget.dimensionColumn)] : [];
        }

        if (!Array.isArray(widget.rowDimensions)) {
            widget.rowDimensions = widget.type === "table" && widget.dimensionColumn ? [String(widget.dimensionColumn)] : [];
        }

        widget.columnDimensions = [];

        const legacyCounterItem = Array.isArray(widget.counterItems) && widget.counterItems.length
            ? widget.counterItems[0]
            : null;

        if (!Array.isArray(widget.measures)) {
            widget.measures = [createMeasureEntry({
                column: legacyCounterItem?.column || widget.valueColumn || "",
                aggregation: widget.aggregation && widget.aggregation !== "percentage"
                    ? widget.aggregation
                    : (legacyCounterItem?.aggregation || defaultMeasureAggregationForWidget(widget)),
            })];
        } else {
            widget.measures = widget.measures.map(function (measure) {
                if (measure && typeof measure === "object") {
                    Object.assign(measure, createMeasureEntry(measure));
                    return measure;
                }

                return createMeasureEntry(measure);
            });
        }

        widget.chartDimensions = widget.chartDimensions.slice(0, 1).map(function (value) {
            return String(value || "");
        });
        widget.rowDimensions = widget.rowDimensions.slice(0, 1).map(function (value) {
            return String(value || "");
        });
        widget.columnDimensions = [];
        widget.measures = widget.measures.slice(0, 1);
        widget.widgetFilters = (Array.isArray(widget.widgetFilters) ? widget.widgetFilters : []).map(function (filter) {
            if (filter && typeof filter === "object") {
                Object.assign(filter, createFilterEntry(filter));
                return filter;
            }

            return createFilterEntry(filter);
        }).slice(0, 5);
        widget.counterItems = [];
        widget.filterColumn = "";
        widget.filterValue = "";
        widget.targetGoal = "";
        widget.percentageBase = widget.type === "percentage" ? "group_share" : "";

        if (widget.type !== "table") {
            widget.rowDimensions = [];
        }

        if (!widget.measures.length) {
            widget.measures.push(createMeasureEntry({ aggregation: defaultMeasureAggregationForWidget(widget) }));
        }

        widget.tableColumns = Array.isArray(widget.tableColumns)
            ? widget.tableColumns.map(function (value) { return String(value || "").trim(); }).filter(Boolean)
            : [];
        widget.tableColumnStyles = (Array.isArray(widget.tableColumnStyles) ? widget.tableColumnStyles : []).map(function (entry) {
            if (entry && typeof entry === "object") {
                Object.assign(entry, createTableColumnStyleEntry(entry));
                return entry;
            }

            return createTableColumnStyleEntry(entry);
        }).filter(function (entry) {
            return entry.key !== "";
        });
        widget.tableStyles = normalizeDatatableStyleConfig(widget.tableStyles);
        widget.sortColumn = String(widget.sortColumn || "");
        widget.sortDir = String(widget.sortDir || "asc") === "desc" ? "desc" : "asc";
    }

    function synchronizeLegacyWidgetFields(widget) {
        ensureWidgetDataModel(widget);

        const primaryMeasure = widget.measures[0] || createMeasureEntry({ aggregation: defaultMeasureAggregationForWidget(widget) });
        syncWidgetFormatWithMeasure(widget, primaryMeasure);

        if (widget.type === "table") {
            widget.dimensionColumn = String(widget.rowDimensions[0] || "");
        } else {
            widget.dimensionColumn = String(widget.chartDimensions[0] || "");
        }

        widget.valueColumn = String(primaryMeasure?.column || "");
        widget.aggregation = widget.type === "percentage"
            ? "percentage"
            : String(primaryMeasure?.aggregation || defaultMeasureAggregationForWidget(widget));
        widget.filterColumn = "";
        widget.filterValue = "";
        widget.targetGoal = "";
        widget.percentageBase = widget.type === "percentage" ? "group_share" : "";
    }

    function createBuilderZone(title, description, addLabel, onAdd, canAdd) {
        const zone = document.createElement("div");
        zone.className = "bi-data-zone";

        const head = document.createElement("div");
        head.className = "bi-data-zone-head";
        head.innerHTML = '<div><h5>' + escapeHtml(title) + '</h5>' + (description ? '<p>' + escapeHtml(description) + '</p>' : '') + '</div>';

        if (addLabel && typeof onAdd === "function") {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "stats-edit-button bi-data-add-button";
            button.textContent = addLabel;
            button.disabled = canAdd === false;
            button.addEventListener("click", function (event) {
                event.preventDefault();
                onAdd();
            });
            head.appendChild(button);
        }

        zone.appendChild(head);
        return zone;
    }

    function createBuilderField(labelText, control, wide) {
        const field = document.createElement("div");
        field.className = "bi-data-field" + (wide ? " is-wide" : "");

        const label = document.createElement("label");
        label.textContent = labelText;
        field.appendChild(label);
        field.appendChild(control);

        return field;
    }

    function setBuilderSelectOptions(select, options, value, placeholderLabel) {
        if (!select) return;

        const normalizedOptions = Array.isArray(options) ? options : [];
        const choices = placeholderLabel ? [{ value: "", label: placeholderLabel }].concat(normalizedOptions) : normalizedOptions;
        select.innerHTML = choices.map(function (option) {
            const optionValue = String(option.value ?? option.key ?? "");
            const optionLabel = String(option.label ?? optionValue);
            const selected = String(value || "") === optionValue ? " selected" : "";
            return '<option value="' + escapeHtml(optionValue) + '"' + selected + '>' + escapeHtml(optionLabel) + '</option>';
        }).join("");
    }

    function createBuilderSelect(options, value, onChange, placeholderLabel) {
        const select = document.createElement("select");
        select.className = "stats-select";
        setBuilderSelectOptions(select, options, value, placeholderLabel);
        select.addEventListener("change", function () {
            onChange(String(select.value || ""));
        });
        return select;
    }

    function createBuilderInput(value, onChange, placeholder, suggestions, onInput) {
        const wrapper = document.createElement("div");
        wrapper.className = "bi-data-input-wrap";

        const input = document.createElement("input");
        input.type = "text";
        input.className = "stats-select bi-text-input";
        input.value = String(value || "");
        input.placeholder = placeholder || "";

        if (Array.isArray(suggestions) && suggestions.length) {
            const listId = createId("datalist");
            input.setAttribute("list", listId);
            const datalist = document.createElement("datalist");
            datalist.id = listId;
            datalist.innerHTML = suggestions.map(function (suggestion) {
                return '<option value="' + escapeAttribute(suggestion) + '"></option>';
            }).join("");
            wrapper.appendChild(datalist);
        }

        input.addEventListener("change", function () {
            onChange(String(input.value || ""));
        });

        if (typeof onInput === "function") {
            input.addEventListener("input", function () {
                onInput(String(input.value || ""));
            });
        }

        wrapper.appendChild(input);
        return wrapper;
    }

    function createBuilderPaletteColorTrigger(options) {
        const safeOptions = options && typeof options === "object" ? options : {};
        const wrapper = document.createElement("div");
        wrapper.className = "color-input-wrapper bi-builder-palette-trigger";
        if (safeOptions.tooltip) {
            wrapper.dataset.tooltip = String(safeOptions.tooltip);
        }

        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "bi-color-trigger bi-color-trigger-large";
        trigger.setAttribute("aria-label", String(safeOptions.ariaLabel || safeOptions.tooltip || "Choisir une couleur"));
        wrapper.appendChild(trigger);

        bindColorTrigger(trigger, {
            fallback: safeOptions.fallback || "#1f2937",
            getValue: function () {
                return safeOptions.getValue?.() || "";
            },
            setValue: function (nextValue) {
                safeOptions.setValue?.(normalizeColorInputValue(nextValue || ""));
            },
        });

        syncColorTrigger(trigger, safeOptions.getValue?.() || "");

        return wrapper;
    }

    function createRemoveButton(onClick, title) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "stats-edit-button bi-icon-button bi-data-remove-button";
        button.title = title || "Supprimer";
        button.setAttribute("aria-label", title || "Supprimer");
        button.innerHTML = '<i class="bi bi-x-lg"></i>';
        button.addEventListener("click", function (event) {
            event.preventDefault();
            onClick();
        });
        return button;
    }

    function createSuggestionInputControl(value, placeholder, suggestions, onChange) {
        const wrapper = document.createElement("div");
        wrapper.className = "bi-data-input-wrap";

        const input = document.createElement("input");
        input.type = "text";
        input.className = "stats-select bi-text-input";

        const datalistId = createId("datalist");
        const datalist = document.createElement("datalist");
        datalist.id = datalistId;

        const sync = function (nextValue, nextPlaceholder, nextSuggestions) {
            input.value = String(nextValue || "");
            input.placeholder = nextPlaceholder || "";

            const normalizedSuggestions = Array.isArray(nextSuggestions) ? nextSuggestions.filter(function (item) {
                return String(item || "").trim() !== "";
            }) : [];

            if (normalizedSuggestions.length) {
                input.setAttribute("list", datalistId);
                datalist.innerHTML = normalizedSuggestions.map(function (suggestion) {
                    return '<option value="' + escapeAttribute(suggestion) + '"></option>';
                }).join("");
            } else {
                input.removeAttribute("list");
                datalist.innerHTML = "";
            }
        };

        input.addEventListener("change", function () {
            onChange(String(input.value || ""));
        });

        sync(value, placeholder, suggestions);
        wrapper.appendChild(datalist);
        wrapper.appendChild(input);

        return {
            wrapper: wrapper,
            input: input,
            sync: sync,
        };
    }

    function createBuilderCheckboxMenu(selectedValues, availableValues, onChange, emptyLabel, renderItemExtra) {
        const wrapper = document.createElement("div");
        wrapper.className = "bi-checkbox-menu";

        const summary = document.createElement("div");
        summary.className = "bi-checkbox-menu-summary";
        wrapper.appendChild(summary);

        const list = document.createElement("div");
        list.className = "bi-checkbox-menu-list";
        wrapper.appendChild(list);

        const render = function (nextSelectedValues, nextAvailableValues) {
            const selected = normalizeFilterSelectionValues(nextSelectedValues);
            const selectedSet = {};
            selected.forEach(function (value) {
                selectedSet[value] = true;
            });

            const values = normalizeFilterSelectionValues(nextAvailableValues);
            summary.textContent = selected.length
                ? (selected.length + " valeur" + (selected.length > 1 ? "s" : "") + " selectionnee" + (selected.length > 1 ? "s" : ""))
                : String(emptyLabel || "Toutes les valeurs");

            list.innerHTML = "";
            if (!values.length) {
                const empty = document.createElement("div");
                empty.className = "bi-checkbox-menu-empty";
                empty.textContent = "Aucune valeur disponible";
                list.appendChild(empty);
                return;
            }

            values.forEach(function (value) {
                const item = document.createElement("div");
                item.className = "bi-checkbox-menu-item";

                const row = document.createElement("label");
                row.className = "bi-checkbox-menu-item-row";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.checked = Boolean(selectedSet[value]);
                checkbox.addEventListener("change", function () {
                    const nextValues = values.filter(function (candidate) {
                        return candidate === value ? checkbox.checked : Boolean(selectedSet[candidate]);
                    });
                    onChange(normalizeFilterSelectionValues(nextValues));
                });

                const text = document.createElement("span");
                text.textContent = value;

                row.appendChild(checkbox);
                row.appendChild(text);
                item.appendChild(row);

                if (checkbox.checked && typeof renderItemExtra === "function") {
                    const extra = renderItemExtra(value);
                    if (extra) {
                        extra.classList.add("bi-checkbox-menu-item-extra");
                        item.appendChild(extra);
                    }
                }

                list.appendChild(item);
            });
        };

        render(selectedValues, availableValues);

        return {
            wrapper: wrapper,
            sync: render,
        };
    }

    function isChartAggregationWidgetType(widget) {
        return ["bar", "bar-horizontal", "pie", "doughnut", "line"].indexOf(String(widget?.type || "")) !== -1;
    }

    function getBuilderAggregationOptions(widget) {
        const options = [
            { value: "count", label: "Nombre de lignes" },
            { value: "sum", label: "Somme" },
            { value: "avg", label: "Moyenne" },
        ];

        if (isChartAggregationWidgetType(widget)) {
            options.push({ value: "percentage", label: "Pourcentage" });
        }

        return options;
    }

    function syncWidgetFormatWithMeasure(widget, measure) {
        const aggregation = String(measure?.aggregation || defaultMeasureAggregationForWidget(widget));
        if (aggregation === "percentage" || widget?.type === "percentage") {
            widget.format = "percent";
            return;
        }

        if (String(widget?.format || "") === "percent") {
            widget.format = "";
        }
    }

    function triggerWidgetDataRefresh(widget, rebuildInspector) {
        synchronizeLegacyWidgetFields(widget);
        refreshWidgetAfterModalEdit(rebuildInspector ? { rebuildInspector: true } : undefined);
    }

    function getWidgetPrimaryMeasure(widget) {
        ensureWidgetDataModel(widget);

        if (!Array.isArray(widget.measures) || !widget.measures.length) {
            widget.measures = [createMeasureEntry({ aggregation: defaultMeasureAggregationForWidget(widget) })];
        } else {
            if (widget.measures[0] && typeof widget.measures[0] === "object") {
                Object.assign(widget.measures[0], createMeasureEntry(widget.measures[0]));
            } else {
                widget.measures[0] = createMeasureEntry(widget.measures[0]);
            }
            widget.measures = widget.measures.slice(0, 1);
        }

        return widget.measures[0];
    }

    function getWidgetBuilderFilters(widget) {
        ensureWidgetDataModel(widget);
        widget.widgetFilters = (Array.isArray(widget.widgetFilters) ? widget.widgetFilters : []).map(function (filter) {
            if (filter && typeof filter === "object") {
                Object.assign(filter, createFilterEntry(filter));
                return filter;
            }

            return createFilterEntry(filter);
        }).slice(0, 5);
        return widget.widgetFilters;
    }

    function getWidgetScopedRows(widget, excludedFilterId) {
        const rows = getFilteredRows();
        if (!widget) {
            return rows;
        }

        const filters = getWidgetBuilderFilters(widget).filter(function (filter) {
            if (!excludedFilterId) {
                return true;
            }

            return String(filter.id || "") !== String(excludedFilterId || "");
        });

        return applyFilterCollection(rows, filters);
    }

    function syncMeasureColumnSelect(select, measure, columns) {
        const options = getMeasureColumnOptions(columns, measure?.aggregation);
        const allowedValues = options.map(function (option) {
            return String(option.value ?? option.key ?? "");
        });

        if (measure && String(measure.column || "") !== "" && allowedValues.indexOf(String(measure.column || "")) === -1) {
            measure.column = "";
        }

        setBuilderSelectOptions(select, options, measure?.column || "");
    }

    function getFirstSelectableMeasureColumn(columns, aggregation) {
        const options = getMeasureColumnOptions(columns, aggregation);
        const firstOption = options.find(function (option) {
            return String(option.value ?? option.key ?? "").trim() !== "";
        });

        return String(firstOption?.value ?? firstOption?.key ?? "");
    }

    function createMeasureBuilderRow(widget, columns, columnLabel) {
        const measure = getWidgetPrimaryMeasure(widget);
        const row = document.createElement("div");
        row.className = "bi-data-row";

        let columnSelect = null;
        let matchValueSelect = null;
        let matchValueField = null;

        const syncMeasureMatchValueSelect = function () {
            const showMatchValue = String(measure?.aggregation || "count") === "count";
            const selectedColumn = String(measure?.column || "").trim();
            if (!matchValueField || !matchValueSelect) {
                return;
            }

            if (!showMatchValue) {
                measure.matchValue = "";
                matchValueField.hidden = true;
                matchValueSelect.disabled = true;
                setBuilderSelectOptions(matchValueSelect, [], "", "Valeur non utilisee pour ce calcul");
                return;
            }

            if (selectedColumn === "") {
                measure.matchValue = "";
                matchValueField.hidden = true;
                matchValueSelect.disabled = true;
                setBuilderSelectOptions(matchValueSelect, [], "", "Choisissez d abord une colonne");
                return;
            }

            matchValueField.hidden = false;
            matchValueSelect.disabled = false;
            const options = buildDistinctValueOptions(selectedColumn, measure.matchValue, getWidgetScopedRows(widget));
            const allowedValues = options.map(function (option) {
                return String(option.value ?? option.key ?? "");
            });
            if (allowedValues.indexOf(String(measure.matchValue || "")) === -1) {
                measure.matchValue = "";
            }
            setBuilderSelectOptions(matchValueSelect, options, measure.matchValue || "");
        };

        const aggregationSelect = createBuilderSelect(getBuilderAggregationOptions(widget), measure.aggregation, function (nextValue) {
            measure.aggregation = nextValue || defaultMeasureAggregationForWidget(widget);
            if (measureNeedsColumn(measure)) {
                const availableValues = getMeasureColumnOptions(columns, measure.aggregation).map(function (option) {
                    return String(option.value ?? option.key ?? "");
                });
                const currentColumn = String(measure.column || "").trim();
                if (currentColumn === "" || availableValues.indexOf(currentColumn) === -1) {
                    measure.column = getFirstSelectableMeasureColumn(columns, measure.aggregation);
                }
            } else if (String(measure.aggregation || "") === "percentage") {
                const allowedValues = getMeasureColumnOptions(columns, measure.aggregation).map(function (option) {
                    return String(option.value ?? option.key ?? "");
                });
                if (allowedValues.indexOf(String(measure.column || "")) === -1) {
                    measure.column = "";
                }
            }
            if (String(measure.aggregation || "count") !== "count") {
                measure.matchValue = "";
            }
            syncWidgetFormatWithMeasure(widget, measure);
            syncMeasureColumnSelect(columnSelect, measure, columns);
            syncMeasureMatchValueSelect();
            triggerWidgetDataRefresh(widget, false);
        });

        columnSelect = createBuilderSelect([], "", function (nextValue) {
            if (String(measure.column || "") !== String(nextValue || "")) {
                measure.matchValue = "";
            }
            measure.column = nextValue;
            syncWidgetFormatWithMeasure(widget, measure);
            syncMeasureMatchValueSelect();
            triggerWidgetDataRefresh(widget, false);
        });
        syncMeasureColumnSelect(columnSelect, measure, columns);

        matchValueSelect = createBuilderSelect([], "", function (nextValue) {
            measure.matchValue = nextValue;
            triggerWidgetDataRefresh(widget, false);
        });

        row.appendChild(createBuilderField("Calcul", aggregationSelect));
        row.appendChild(createBuilderField(columnLabel || "Valeur", columnSelect));
        matchValueField = createBuilderField("Valeur a compter", matchValueSelect);
        row.appendChild(matchValueField);
        syncMeasureMatchValueSelect();

        return row;
    }

    function renderSimpleFilterZone(widget, columns, title, description) {
        const filters = getWidgetBuilderFilters(widget);
        const section = createBuilderZone(title, description, filters.length < 5 ? "+ Ajouter" : "", function () {
            if (widget.widgetFilters.length < 5) {
                widget.widgetFilters.push(createFilterEntry({}));
                triggerWidgetDataRefresh(widget, true);
            }
        }, filters.length < 5);
        const list = document.createElement("div");
        list.className = "bi-data-list";

        if (!filters.length) {
            const empty = document.createElement("div");
            empty.className = "bi-data-empty";
            empty.textContent = "Aucun filtre configure.";
            list.appendChild(empty);
        }

        filters.forEach(function (filter, index) {
            const row = document.createElement("div");
            row.className = "bi-data-row bi-datatable-filter-grid";
            const getSelectedValue = function () {
                return String((Array.isArray(filter.values) && filter.values.length ? filter.values[0] : filter.value) || "");
            };

            const valueSelect = createBuilderSelect([], "", function (nextValue) {
                filter.values = nextValue ? [String(nextValue)] : [];
                filter.value = "";
                triggerWidgetDataRefresh(widget, false);
            });

            const syncValueSelect = function (nextColumn) {
                const selectedColumn = String(nextColumn || "");
                const selectedValue = getSelectedValue();
                const options = selectedColumn
                    ? buildDistinctValueOptions(selectedColumn, selectedValue, getWidgetScopedRows(widget, filter.id))
                    : [];
                const allowedValues = options.map(function (option) {
                    return String(option.value ?? option.key ?? "");
                });

                if (selectedColumn === "") {
                    filter.value = "";
                    filter.values = [];
                    valueSelect.disabled = true;
                    setBuilderSelectOptions(valueSelect, [], "", "Selectionnez d abord une colonne");
                    return;
                }

                if (allowedValues.indexOf(selectedValue) === -1) {
                    filter.value = "";
                    filter.values = [];
                }

                valueSelect.disabled = false;
                setBuilderSelectOptions(valueSelect, options, getSelectedValue(), "Toutes les valeurs");
            };

            const columnSelect = createBuilderSelect(columns.map(function (column) {
                return { value: column.key, label: column.label };
            }), filter.column, function (nextValue) {
                filter.column = nextValue;
                filter.value = "";
                filter.values = [];
                syncValueSelect(nextValue);
                triggerWidgetDataRefresh(widget, false);
            }, "Selectionner une colonne");

            row.appendChild(createBuilderField("Colonne", columnSelect));
            row.appendChild(createBuilderField("Valeur a filtrer", valueSelect));
            row.appendChild(createRemoveButton(function () {
                widget.widgetFilters.splice(index, 1);
                triggerWidgetDataRefresh(widget, true);
            }, "Supprimer ce filtre"));
            syncValueSelect(filter.column);
            list.appendChild(row);
        });

        section.appendChild(list);
        return section;
    }

    function renderDimensionCollection(zone, title, description, items, columns, labelPrefix, onAdd, onChange, onRemove, maxItems, single) {
        const canAdd = single ? false : items.length < maxItems;
        const section = createBuilderZone(title, description, single ? "" : "+ Ajouter", onAdd, canAdd);
        const list = document.createElement("div");
        list.className = "bi-data-list";
        const entries = items.length ? items : [""];
        const dimensionChoices = getDimensionColumns(columns).map(function (column) {
            return { value: column.key, label: column.label };
        });

        entries.forEach(function (value, index) {
            const row = document.createElement("div");
            row.className = "bi-data-row";
            const select = createBuilderSelect(dimensionChoices, value, function (nextValue) {
                items[index] = nextValue;
                if (!single && nextValue === "" && items.length > 1) {
                    items.splice(index, 1);
                }
                triggerWidgetDataRefresh(getSelectedWidget(), false);
            }, "Selectionner une colonne");
            row.appendChild(createBuilderField(entries.length === 1 && single ? labelPrefix : (labelPrefix + " " + (index + 1)), select, true));
            if (!single && items.length > 1) {
                row.appendChild(createRemoveButton(function () {
                    items.splice(index, 1);
                    triggerWidgetDataRefresh(getSelectedWidget(), true);
                }, "Supprimer cet element"));
            }
            list.appendChild(row);
        });

        section.appendChild(list);
        return section;
    }

    function renderMeasureCollection(zoneTitle, description, measures, columns, onAdd, maxItems, singleLabel) {
        const canAdd = maxItems > 1 && measures.length < maxItems;
        const section = createBuilderZone(zoneTitle, description, canAdd ? "+ Ajouter" : "", onAdd, canAdd);
        const list = document.createElement("div");
        list.className = "bi-data-list";
        const entries = measures.length ? measures : [createMeasureEntry({})];

        entries.forEach(function (measure, index) {
            if (!measures[index]) {
                measures[index] = createMeasureEntry(measure);
            }

            const row = document.createElement("div");
            row.className = "bi-data-row";
            row.appendChild(createBuilderField("Calcul", createBuilderSelect(getBuilderAggregationOptions(), measures[index].aggregation, function (nextValue) {
                measures[index].aggregation = nextValue;
                triggerWidgetDataRefresh(getSelectedWidget(), true);
            })));
            row.appendChild(createBuilderField(singleLabel || ("Mesure " + (index + 1)), createBuilderSelect(
                getMeasureColumnOptions(columns, measures[index].aggregation),
                measures[index].column,
                function (nextValue) {
                    measures[index].column = nextValue;
                    triggerWidgetDataRefresh(getSelectedWidget(), false);
                },
            ), true));
            if (measures.length > 1) {
                row.appendChild(createRemoveButton(function () {
                    measures.splice(index, 1);
                    triggerWidgetDataRefresh(getSelectedWidget(), true);
                }, "Supprimer cette mesure"));
            }
            list.appendChild(row);
        });

        section.appendChild(list);
        return section;
    }

    function renderFilterCollection(zoneTitle, description, filters, columns, onAdd, maxItems) {
        const section = createBuilderZone(zoneTitle, description, filters.length < maxItems ? "+ Ajouter" : "", onAdd, filters.length < maxItems);
        const list = document.createElement("div");
        list.className = "bi-data-list";

        if (!filters.length) {
            const empty = document.createElement("div");
            empty.className = "bi-data-empty";
            empty.textContent = "Aucun filtre configure.";
            list.appendChild(empty);
        }

        filters.forEach(function (filter, index) {
            const row = document.createElement("div");
            row.className = "bi-data-row";
            row.appendChild(createBuilderField("Colonne", createBuilderSelect(columns.map(function (column) {
                return { value: column.key, label: column.label };
            }), filter.column, function (nextValue) {
                filter.column = nextValue;
                filter.value = "";
                triggerWidgetDataRefresh(getSelectedWidget(), true);
            }, "Selectionner une colonne")));
            row.appendChild(createBuilderField("Valeur", createBuilderInput(filter.value, function (nextValue) {
                filter.value = nextValue;
                triggerWidgetDataRefresh(getSelectedWidget(), false);
            }, filter.column ? "Choisissez ou saisissez une valeur" : "Selectionnez d abord une colonne", getDistinctColumnValues(filter.column), function (nextValue) {
                filter.value = nextValue;
                triggerWidgetDataRefresh(getSelectedWidget(), false);
            }), true));
            row.appendChild(createRemoveButton(function () {
                filters.splice(index, 1);
                triggerWidgetDataRefresh(getSelectedWidget(), true);
            }, "Supprimer ce filtre"));
            list.appendChild(row);
        });

        section.appendChild(list);
        return section;
    }

    function renderChoiceZone(title, description, label, options, value, onChange) {
        const section = createBuilderZone(title, description, "", null, false);
        const row = document.createElement("div");
        row.className = "bi-data-row";
        row.appendChild(createBuilderField(label, createBuilderSelect(options, value, onChange), true));
        section.appendChild(row);
        return section;
    }

    function renderColumnChoiceZone(title, description, label, columns, value, onChange, numericOnly, emptyLabel) {
        const choices = (numericOnly ? getNumericColumns(columns) : getDimensionColumns(columns)).map(function (column) {
            return { value: column.key, label: column.label };
        });

        return renderChoiceZone(title, description, label, [{ value: "", label: emptyLabel || "Selectionner une colonne" }].concat(choices), value, onChange);
    }

    function renderNumberInputZone(title, description, label, value, placeholder, onChange) {
        const section = createBuilderZone(title, description, "", null, false);
        const row = document.createElement("div");
        row.className = "bi-data-row";
        row.appendChild(createBuilderField(label, createBuilderInput(value, onChange, placeholder, [], function (nextValue) {
            onChange(nextValue);
        }), true));
        section.appendChild(row);
        return section;
    }

    function renderFormatZone(widget, allowPercent) {
        const options = [
            { value: "", label: "Standard" },
            { value: "number", label: "Nombre" },
            { value: "currency", label: "Monetaire" },
        ];

        if (allowPercent) {
            options.push({ value: "percent", label: "Pourcentage" });
        }

        return renderChoiceZone(
            "Affichage de la valeur",
            "Choisissez comment afficher le resultat principal.",
            "Format",
            options,
            widget.format || "",
            function (nextValue) {
                widget.format = nextValue;
                triggerWidgetDataRefresh(widget, false);
            },
        );
    }

    function renderMaxItemsZone(widget, label) {
        return renderChoiceZone(
            "Affichage",
            "Limitez le volume affiche pour garder un composant lisible.",
            label,
            [
                { value: "5", label: "5" },
                { value: "8", label: "8" },
                { value: "10", label: "10" },
                { value: "12", label: "12" },
                { value: "15", label: "15" },
                { value: "20", label: "20" },
            ],
            String(widget.maxItems || 8),
            function (nextValue) {
                widget.maxItems = clamp(parseInt(nextValue, 10) || 8, 3, 20);
                triggerWidgetDataRefresh(widget, false);
            },
        );
    }

    function renderDatatableColumnsZone(widget, columns) {
        const section = createBuilderZone("Colonnes", "Choisissez les colonnes visibles dans le tableau personnalise.", "", null, false);
        const actions = document.createElement("div");
        actions.className = "bi-datatable-col-actions";
        const summary = document.createElement("div");
        summary.className = "bi-datatable-col-summary";

        const btnAll = document.createElement("button");
        btnAll.type = "button";
        btnAll.className = "stats-edit-button";
        btnAll.textContent = "Tout selectionner";

        const btnNone = document.createElement("button");
        btnNone.type = "button";
        btnNone.className = "stats-edit-button";
        btnNone.textContent = "Vider";

        actions.appendChild(summary);

        const actionButtons = document.createElement("div");
        actionButtons.className = "bi-datatable-col-action-buttons";
        actionButtons.appendChild(btnAll);
        actionButtons.appendChild(btnNone);
        actions.appendChild(actionButtons);
        section.appendChild(actions);

        const grid = document.createElement("div");
        grid.className = "bi-datatable-col-grid";
        const head = document.createElement("div");
        head.className = "bi-datatable-col-head";
        head.innerHTML = '<span>Titre</span><span>Couleurs</span><span>Affichage</span>';
        grid.appendChild(head);

        const selectedColumns = Array.isArray(widget.tableColumns) ? widget.tableColumns : [];
        const getColumnStyleEntry = function (columnKey, createIfMissing) {
            const normalizedKey = String(columnKey || "");
            widget.tableColumnStyles = Array.isArray(widget.tableColumnStyles) ? widget.tableColumnStyles : [];
            let entry = widget.tableColumnStyles.find(function (candidate) {
                return String(candidate?.key || "") === normalizedKey;
            }) || null;
            if (!entry && createIfMissing) {
                entry = createTableColumnStyleEntry({ key: normalizedKey });
                widget.tableColumnStyles.push(entry);
            }
            return entry;
        };
        const setColumnStyleValue = function (columnKey, field, nextValue) {
            const entry = getColumnStyleEntry(columnKey, true);
            if (!entry) {
                return;
            }

            entry[field] = normalizeColorInputValue(nextValue || "");
            triggerWidgetDataRefresh(widget, false);
        };
        const refreshSelectionState = function () {
            const checked = Array.from(grid.querySelectorAll(".bi-datatable-col-checkbox:checked")).map(function (item) {
                return String(item.value || "");
            }).filter(Boolean);
            widget.tableColumns = checked;
            if (widget.sortColumn && checked.indexOf(widget.sortColumn) === -1) {
                widget.sortColumn = "";
            }
            summary.textContent = checked.length + " / " + columns.length + " colonne" + (columns.length > 1 ? "s" : "") + " affichee" + (checked.length > 1 ? "s" : "");
        };

        columns.forEach(function (column) {
            const row = document.createElement("div");
            row.className = "bi-datatable-col-row";

            const name = document.createElement("div");
            name.className = "bi-datatable-col-name";
            name.textContent = String(column.label || column.key || "");

            const colors = document.createElement("div");
            colors.className = "bi-datatable-col-colors";
            colors.appendChild(createBuilderPaletteColorTrigger({
                tooltip: "Fond",
                ariaLabel: "Couleur de fond pour " + String(column.label || column.key || ""),
                fallback: "#1f2937",
                getValue: function () {
                    return getColumnStyleEntry(column.key, false)?.bgColor || "";
                },
                setValue: function (nextValue) {
                    setColumnStyleValue(column.key, "bgColor", nextValue);
                },
            }));
            colors.appendChild(createBuilderPaletteColorTrigger({
                tooltip: "Texte",
                ariaLabel: "Couleur du texte pour " + String(column.label || column.key || ""),
                fallback: "#f8fafc",
                getValue: function () {
                    return getColumnStyleEntry(column.key, false)?.textColor || "";
                },
                setValue: function (nextValue) {
                    setColumnStyleValue(column.key, "textColor", nextValue);
                },
            }));

            const visibility = document.createElement("label");
            visibility.className = "bi-datatable-col-visibility";

            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.className = "bi-datatable-col-checkbox";
            checkbox.value = column.key;
            checkbox.checked = selectedColumns.indexOf(column.key) !== -1;
            const stateLabel = document.createElement("span");
            stateLabel.className = "bi-datatable-col-state";
            stateLabel.textContent = checkbox.checked ? "Afficher" : "Ne pas afficher";
            checkbox.addEventListener("change", function () {
                stateLabel.textContent = checkbox.checked ? "Afficher" : "Ne pas afficher";
                refreshSelectionState();
                triggerWidgetDataRefresh(widget, false);
            });

            visibility.appendChild(checkbox);
            visibility.appendChild(stateLabel);
            row.appendChild(name);
            row.appendChild(colors);
            row.appendChild(visibility);
            grid.appendChild(row);
        });

        btnAll.addEventListener("click", function () {
            grid.querySelectorAll(".bi-datatable-col-checkbox").forEach(function (item) {
                item.checked = true;
                const stateLabel = item.parentElement?.querySelector(".bi-datatable-col-state");
                if (stateLabel) {
                    stateLabel.textContent = "Afficher";
                }
            });
            refreshSelectionState();
            triggerWidgetDataRefresh(widget, false);
        });

        btnNone.addEventListener("click", function () {
            grid.querySelectorAll(".bi-datatable-col-checkbox").forEach(function (item) {
                item.checked = false;
                const stateLabel = item.parentElement?.querySelector(".bi-datatable-col-state");
                if (stateLabel) {
                    stateLabel.textContent = "Ne pas afficher";
                }
            });
            refreshSelectionState();
            triggerWidgetDataRefresh(widget, false);
        });

        refreshSelectionState();
        section.appendChild(grid);
        return section;
    }

    function renderDatatableFilterZone(widget, columns) {
        const filters = getWidgetBuilderFilters(widget);
        const section = createBuilderZone(
            "Filtres du tableau",
            "Filtrez sur une valeur exacte ou sur un texte partiel, avec liste dynamique ou saisie libre.",
            filters.length < 5 ? "+ Ajouter" : "",
            function () {
                if (widget.widgetFilters.length < 5) {
                    widget.widgetFilters.push(createFilterEntry({}));
                    triggerWidgetDataRefresh(widget, true);
                }
            },
            filters.length < 5
        );
        const list = document.createElement("div");
        list.className = "bi-data-list";

        if (!filters.length) {
            const empty = document.createElement("div");
            empty.className = "bi-data-empty";
            empty.textContent = "Aucun filtre configure.";
            list.appendChild(empty);
        }

        filters.forEach(function (filter, index) {
            const row = document.createElement("div");
            row.className = "bi-data-row";

            const valueField = createBuilderField("Valeur", document.createElement("div"), true);
            let valueChecklist = null;
            const getValueStyleEntry = function (targetValue, createIfMissing) {
                const normalizedValue = String(targetValue || "").trim();
                filter.valueStyles = normalizeFilterValueStyles(filter.valueStyles, filter.values, filter);
                let entry = filter.valueStyles.find(function (candidate) {
                    return String(candidate?.value || "") === normalizedValue;
                }) || null;
                if (!entry && createIfMissing) {
                    entry = createFilterValueStyleEntry({ value: normalizedValue });
                    filter.valueStyles.push(entry);
                }
                return entry;
            };
            const pruneValueStyles = function () {
                filter.valueStyles = normalizeFilterValueStyles(filter.valueStyles, filter.values, filter);
            };

            const syncValueControl = function () {
                const selectedColumn = String(filter.column || "");
                valueField.innerHTML = "";
                valueField.appendChild((function () {
                    const label = document.createElement("label");
                    label.textContent = "Valeur";
                    return label;
                })());

                if (filter.inputMode === "input") {
                    valueField.appendChild(createBuilderInput(
                        filter.value,
                        function (nextValue) {
                            filter.value = nextValue;
                            triggerWidgetDataRefresh(widget, false);
                        },
                        selectedColumn ? "Saisir une valeur" : "Selectionnez d abord une colonne",
                        selectedColumn ? getDistinctColumnValues(selectedColumn, getWidgetScopedRows(widget, filter.id)) : [],
                        function (nextValue) {
                            filter.value = nextValue;
                            triggerWidgetDataRefresh(widget, false);
                        }
                    ));
                    return;
                }

                if (!selectedColumn) {
                    valueChecklist = createBuilderCheckboxMenu([], [], function () {
                        return null;
                    }, "Selectionnez d abord une colonne");
                    valueField.appendChild(valueChecklist.wrapper);
                } else {
                    const values = getDistinctColumnValues(selectedColumn, getWidgetScopedRows(widget, filter.id));
                    const nextSelectedValues = normalizeFilterSelectionValues(filter.values, filter.value).filter(function (value) {
                        return values.indexOf(value) !== -1;
                    });
                    filter.values = nextSelectedValues;
                    filter.value = "";
                    pruneValueStyles();
                    valueChecklist = createBuilderCheckboxMenu(nextSelectedValues, values, function (nextValues) {
                        filter.values = normalizeFilterSelectionValues(nextValues);
                        filter.value = "";
                        pruneValueStyles();
                        triggerWidgetDataRefresh(widget, true);
                    }, "Toutes les valeurs", function (selectedValue) {
                        const styleEntry = getValueStyleEntry(selectedValue, true);
                        const tools = document.createElement("div");
                        tools.className = "bi-checkbox-menu-item-tools";

                        const targetWrap = document.createElement("div");
                        const targetLabel = document.createElement("div");
                        targetLabel.className = "bi-checkbox-menu-item-tools-label";
                        targetLabel.textContent = "Cible";
                        targetWrap.appendChild(targetLabel);
                        targetWrap.appendChild(createBuilderSelect([
                            { value: "none", label: "Aucune" },
                            { value: "row", label: "Ligne" },
                            { value: "cell", label: "Cellule" },
                        ], styleEntry?.styleTarget || "none", function (nextValue) {
                            const current = getValueStyleEntry(selectedValue, true);
                            current.styleTarget = ["none", "row", "cell"].indexOf(String(nextValue || "")) !== -1 ? String(nextValue) : "none";
                            triggerWidgetDataRefresh(widget, false);
                        }));

                        const bgWrap = document.createElement("div");
                        const bgLabel = document.createElement("div");
                        bgLabel.className = "bi-checkbox-menu-item-tools-label";
                        bgLabel.textContent = "Fond";
                        bgWrap.appendChild(bgLabel);
                        bgWrap.appendChild(createBuilderPaletteColorTrigger({
                            tooltip: "Fond",
                            ariaLabel: "Couleur de fond pour " + selectedValue,
                            fallback: "#1f2937",
                            getValue: function () {
                                return getValueStyleEntry(selectedValue, true)?.bgColor || "";
                            },
                            setValue: function (nextValue) {
                                const current = getValueStyleEntry(selectedValue, true);
                                current.bgColor = normalizeColorInputValue(nextValue || "");
                                triggerWidgetDataRefresh(widget, false);
                            },
                        }));

                        const textWrap = document.createElement("div");
                        const textLabel = document.createElement("div");
                        textLabel.className = "bi-checkbox-menu-item-tools-label";
                        textLabel.textContent = "Texte";
                        textWrap.appendChild(textLabel);
                        textWrap.appendChild(createBuilderPaletteColorTrigger({
                            tooltip: "Texte",
                            ariaLabel: "Couleur du texte pour " + selectedValue,
                            fallback: "#f8fafc",
                            getValue: function () {
                                return getValueStyleEntry(selectedValue, true)?.textColor || "";
                            },
                            setValue: function (nextValue) {
                                const current = getValueStyleEntry(selectedValue, true);
                                current.textColor = normalizeColorInputValue(nextValue || "");
                                triggerWidgetDataRefresh(widget, false);
                            },
                        }));

                        tools.appendChild(targetWrap);
                        tools.appendChild(bgWrap);
                        tools.appendChild(textWrap);
                        return tools;
                    });
                    valueField.appendChild(valueChecklist.wrapper);
                }
            };

            row.appendChild(createBuilderField("Colonne", createBuilderSelect(columns.map(function (column) {
                return { value: column.key, label: column.label };
            }), filter.column, function (nextValue) {
                filter.column = nextValue;
                filter.value = "";
                filter.values = [];
                filter.valueStyles = [];
                syncValueControl();
                triggerWidgetDataRefresh(widget, true);
            }, "Selectionner une colonne")));

            row.appendChild(createBuilderField("Recherche", createBuilderSelect([
                { value: "equals", label: "Valeur exacte" },
                { value: "contains", label: "Contient" },
            ], filter.operator || "equals", function (nextValue) {
                filter.operator = nextValue || "equals";
                triggerWidgetDataRefresh(widget, false);
            })));

            row.appendChild(createBuilderField("Saisie", createBuilderSelect([
                { value: "select", label: "Menu dynamique" },
                { value: "input", label: "Champ libre" },
            ], filter.inputMode || "select", function (nextValue) {
                filter.inputMode = nextValue || "select";
                filter.value = "";
                filter.values = [];
                filter.valueStyles = [];
                syncValueControl();
                triggerWidgetDataRefresh(widget, true);
            })));

            syncValueControl();
            row.appendChild(valueField);
            row.appendChild(createRemoveButton(function () {
                widget.widgetFilters.splice(index, 1);
                triggerWidgetDataRefresh(widget, true);
            }, "Supprimer ce filtre"));

            const filterCard = document.createElement("div");
            filterCard.className = "bi-datatable-filter-card";
            filterCard.appendChild(row);
            if (filter.inputMode === "input") {
                const styleRow = document.createElement("div");
                styleRow.className = "bi-data-row bi-datatable-filter-style-row";
                styleRow.appendChild(createBuilderField("Cible couleur", createBuilderSelect([
                    { value: "none", label: "Aucune" },
                    { value: "row", label: "Ligne" },
                    { value: "cell", label: "Cellule" },
                ], filter.styleTarget || "none", function (nextValue) {
                    filter.styleTarget = ["none", "row", "cell"].indexOf(String(nextValue || "")) !== -1 ? String(nextValue) : "none";
                    triggerWidgetDataRefresh(widget, false);
                })));
                styleRow.appendChild(createBuilderField("Fond", createBuilderPaletteColorTrigger({
                    tooltip: "Fond",
                    ariaLabel: "Couleur de fond du filtre",
                    fallback: "#1f2937",
                    getValue: function () {
                        return filter.bgColor || "";
                    },
                    setValue: function (nextValue) {
                        filter.bgColor = normalizeColorInputValue(nextValue || "");
                        triggerWidgetDataRefresh(widget, false);
                    },
                })));
                styleRow.appendChild(createBuilderField("Texte", createBuilderPaletteColorTrigger({
                    tooltip: "Texte",
                    ariaLabel: "Couleur du texte du filtre",
                    fallback: "#f8fafc",
                    getValue: function () {
                        return filter.textColor || "";
                    },
                    setValue: function (nextValue) {
                        filter.textColor = normalizeColorInputValue(nextValue || "");
                        triggerWidgetDataRefresh(widget, false);
                    },
                })));
                filterCard.appendChild(styleRow);
            }
            list.appendChild(filterCard);
        });

        section.appendChild(list);
        return section;
    }

    function renderWidgetDataBuilder(widget, columns) {
        if (!dom.widgetDataBuilder) {
            return;
        }

        ensureWidgetDataModel(widget);
        synchronizeLegacyWidgetFields(widget);

        dom.widgetDataBuilder.innerHTML = "";
        dom.widgetDataBuilder.hidden = false;
        if (dom.widgetDataDescription) {
            dom.widgetDataDescription.textContent = getWidgetDataDescription(widget);
        }

        const type = String(widget.type || "bar");
        const builder = dom.widgetDataBuilder;
        const dimensionChoices = getDimensionColumns(columns).map(function (column) {
            return { value: column.key, label: column.label };
        });
        const numericChoices = getNumericColumns(columns).map(function (column) {
            return { value: column.key, label: column.label };
        });
        const limitLabel = type === "line" ? "Nombre de points" : "Nombre de categories";
        const appendFilterZone = function () {
            builder.appendChild(renderSimpleFilterZone(
                widget,
                columns,
                "Filtres",
                "Ajoutez jusqu a 5 filtres simples du type colonne = valeur pour limiter les lignes prises en compte."
            ));
        };

        if (type === "datatable") {
            builder.appendChild(renderDatatableColumnsZone(widget, columns));
            builder.appendChild(renderDatatableFilterZone(widget, columns));

            const displaySection = createBuilderZone("Affichage", "Reglez le volume et le tri initial du tableau.", "", null, false);
            const displayRow = document.createElement("div");
            displayRow.className = "bi-data-row";
            displayRow.appendChild(createBuilderField("Lignes max", createBuilderSelect([
                { value: "10", label: "10" },
                { value: "20", label: "20" },
                { value: "50", label: "50" },
                { value: "100", label: "100" },
            ], String(widget.maxItems || 20), function (nextValue) {
                widget.maxItems = clamp(parseInt(nextValue, 10) || 20, 5, 100);
                triggerWidgetDataRefresh(widget, false);
            })));
            displayRow.appendChild(createBuilderField("Tri initial", createBuilderSelect([
                { value: "", label: "Aucun tri" },
            ].concat(columns.map(function (column) {
                return { value: column.key, label: column.label };
            })), String(widget.sortColumn || ""), function (nextValue) {
                widget.sortColumn = nextValue;
                triggerWidgetDataRefresh(widget, false);
            })));
            displayRow.appendChild(createBuilderField("Sens", createBuilderSelect([
                { value: "asc", label: "Croissant" },
                { value: "desc", label: "Decroissant" },
            ], String(widget.sortDir || "asc"), function (nextValue) {
                widget.sortDir = nextValue === "desc" ? "desc" : "asc";
                triggerWidgetDataRefresh(widget, false);
            })));
            displaySection.appendChild(displayRow);
            builder.appendChild(displaySection);
            return;
        }

        if (type === "table") {
            const section = createBuilderZone("Tableau", "Choisissez une ligne et une valeur simple a resumer.", "", null, false);

            const firstRow = document.createElement("div");
            firstRow.className = "bi-data-row";
            firstRow.appendChild(createBuilderField("Ligne", createBuilderSelect(dimensionChoices, widget.rowDimensions[0] || "", function (nextValue) {
                widget.rowDimensions = nextValue ? [nextValue] : [];
                triggerWidgetDataRefresh(widget, false);
            }, "Selectionner une colonne")));
            section.appendChild(firstRow);

            section.appendChild(createMeasureBuilderRow(widget, columns, "Valeur"));

            const displayRow = document.createElement("div");
            displayRow.className = "bi-data-row";
            displayRow.appendChild(createBuilderField("Nombre de lignes", createBuilderSelect([
                { value: "5", label: "5" },
                { value: "8", label: "8" },
                { value: "10", label: "10" },
                { value: "12", label: "12" },
                { value: "15", label: "15" },
                { value: "20", label: "20" },
            ], String(widget.maxItems || 10), function (nextValue) {
                widget.maxItems = clamp(parseInt(nextValue, 10) || 10, 3, 20);
                triggerWidgetDataRefresh(widget, false);
            })));
            section.appendChild(displayRow);

            builder.appendChild(section);
            appendFilterZone();
            return;
        }

        if (type === "distribution-table") {
            const section = createBuilderZone("Repartition", "Choisissez une colonne et le tableau affichera automatiquement le nombre, le pourcentage et une jauge coloree.", "", null, false);

            const row = document.createElement("div");
            row.className = "bi-data-row";
            row.appendChild(createBuilderField("Colonne", createBuilderSelect(dimensionChoices, widget.chartDimensions[0] || "", function (nextValue) {
                widget.chartDimensions = nextValue ? [nextValue] : [];
                triggerWidgetDataRefresh(widget, false);
            }, "Selectionner une colonne")));
            row.appendChild(createBuilderField("Nombre de lignes", createBuilderSelect([
                { value: "5", label: "5" },
                { value: "8", label: "8" },
                { value: "10", label: "10" },
                { value: "12", label: "12" },
                { value: "15", label: "15" },
                { value: "20", label: "20" },
            ], String(widget.maxItems || 10), function (nextValue) {
                widget.maxItems = clamp(parseInt(nextValue, 10) || 10, 3, 20);
                triggerWidgetDataRefresh(widget, false);
            })));
            section.appendChild(row);

            builder.appendChild(section);
            appendFilterZone();
            return;
        }

        if (type === "counter") {
            const section = createBuilderZone("Compteur", "Choisissez simplement ce que le compteur doit afficher.", "", null, false);
            section.appendChild(createMeasureBuilderRow(widget, columns, "Colonne"));
            builder.appendChild(section);
            appendFilterZone();
            return;
        }

        if (type === "kpi") {
            const section = createBuilderZone("KPI", "Selectionnez la valeur principale du KPI.", "", null, false);
            section.appendChild(createMeasureBuilderRow(widget, columns, "Colonne"));
            builder.appendChild(section);
            appendFilterZone();
            return;
        }

        if (type === "percentage") {
            const section = createBuilderZone("Pourcentage", "Choisissez la categorie a comparer. La part principale sera calculee automatiquement.", "", null, false);

            const firstRow = document.createElement("div");
            firstRow.className = "bi-data-row";
            firstRow.appendChild(createBuilderField("Categorie", createBuilderSelect(dimensionChoices, widget.chartDimensions[0] || "", function (nextValue) {
                widget.chartDimensions = nextValue ? [nextValue] : [];
                triggerWidgetDataRefresh(widget, false);
            }, "Selectionner une colonne")));
            section.appendChild(firstRow);

            section.appendChild(createMeasureBuilderRow(widget, columns, "Colonne"));
            builder.appendChild(section);
            appendFilterZone();
            return;
        }

        if (type === "histogram") {
            const section = createBuilderZone("Histogramme", "Choisissez une colonne numerique et le nombre de classes a afficher.", "", null, false);
            const measure = getWidgetPrimaryMeasure(widget);
            measure.aggregation = "count";

            const row = document.createElement("div");
            row.className = "bi-data-row";
            row.appendChild(createBuilderField("Colonne numerique", createBuilderSelect(numericChoices, measure.column || "", function (nextValue) {
                measure.column = nextValue;
                triggerWidgetDataRefresh(widget, false);
            }, "Selectionner une colonne numerique")));
            row.appendChild(createBuilderField("Nombre de classes", createBuilderSelect([
                { value: "5", label: "5" },
                { value: "8", label: "8" },
                { value: "10", label: "10" },
                { value: "12", label: "12" },
                { value: "15", label: "15" },
                { value: "20", label: "20" },
            ], String(widget.maxItems || 8), function (nextValue) {
                widget.maxItems = clamp(parseInt(nextValue, 10) || 8, 3, 20);
                triggerWidgetDataRefresh(widget, false);
            })));
            section.appendChild(row);

            builder.appendChild(section);
            appendFilterZone();
            return;
        }

        const section = createBuilderZone("Graphique", "Choisissez un axe X et une seule valeur a calculer.", "", null, false);

        const firstRow = document.createElement("div");
        firstRow.className = "bi-data-row";
        firstRow.appendChild(createBuilderField("Axe X", createBuilderSelect(dimensionChoices, widget.chartDimensions[0] || "", function (nextValue) {
            widget.chartDimensions = nextValue ? [nextValue] : [];
            triggerWidgetDataRefresh(widget, false);
        }, "Selectionner une colonne")));
        firstRow.appendChild(createBuilderField(limitLabel, createBuilderSelect([
            { value: "5", label: "5" },
            { value: "8", label: "8" },
            { value: "10", label: "10" },
            { value: "12", label: "12" },
            { value: "15", label: "15" },
            { value: "20", label: "20" },
        ], String(widget.maxItems || 8), function (nextValue) {
            widget.maxItems = clamp(parseInt(nextValue, 10) || 8, 3, 20);
            triggerWidgetDataRefresh(widget, false);
        })));
        section.appendChild(firstRow);

        section.appendChild(createMeasureBuilderRow(widget, columns, "Valeur"));

        builder.appendChild(section);
        appendFilterZone();
    }

    function renderSettingsModal() {
        renderCreationPermissionsForm();
        renderPagePermissionsForm();
        syncSettingsTabs();
        if (!dom.settingsSourcesList) return;
        const items = [];
        (state.moduleSettings.uploadedSources || []).forEach(function (source) {
            items.push({
                id: source.id,
                sourceType: "uploaded",
                label: source.label || source.fileName || "Source importee",
                meta: [source.fileName || "", source.uploadedAt ? "Importe le " + formatIsoDate(source.uploadedAt) : "Source locale"].filter(Boolean).join(" • "),
                subtitle: source.path || "",
                kind: "Fichier du site",
            });
        });
        (state.moduleSettings.remoteSources || []).forEach(function (source) {
            items.push({
                id: source.id,
                sourceType: "remote",
                label: source.label || "URL SharePoint",
                meta: [source.url || "", source.createdAt ? "Ajoutee le " + formatIsoDate(source.createdAt) : "Source distante"].filter(Boolean).join(" • "),
                subtitle: "URL SharePoint",
                kind: "URL distante",
            });
        });

        (state.moduleSettings.apiSources || []).forEach(function (source) {
            items.push({
                id: source.id,
                sourceType: "api",
                label: source.label || "Webservice JSON",
                meta: [
                    source.url || "",
                    source.tokenConfigured ? ("Token configure" + (source.tokenPreview ? " (" + source.tokenPreview + ")" : "")) : "Sans token",
                    source.createdAt ? "Ajoute le " + formatIsoDate(source.createdAt) : "Source API",
                ].filter(Boolean).join(" â€¢ "),
                subtitle: "API JSON",
                kind: "Webservice",
            });
        });

        if (!items.length) {
            if (state.editingModuleSourceId) {
                resetEditSourceForm();
            }
            dom.settingsSourcesList.innerHTML = '<div class="bi-settings-empty">Aucune source personnalisee configuree pour le moment.</div>';
            return;
        }

        dom.settingsSourcesList.innerHTML = items.map(function (item) {
            return '<div class="bi-settings-source-item">' +
                '<div class="bi-settings-source-meta"><strong>' + escapeHtml(item.label) + '</strong><small>' + escapeHtml(item.kind) + '</small><small>' + escapeHtml(item.meta) + '</small></div>' +
                '<div class="bi-settings-source-actions">' +
                '<button type="button" class="stats-edit-button bi-icon-button bi-settings-source-edit" data-edit-source="' + escapeHtml(item.id) + '" title="Modifier cette source" aria-label="Modifier cette source"><i class="bi bi-pencil"></i></button>' +
                '<button type="button" class="stats-edit-button bi-icon-button bi-settings-source-remove" data-remove-source="' + escapeHtml(item.id) + '" title="Supprimer cette source" aria-label="Supprimer cette source"><i class="bi bi-trash3"></i></button>' +
                "</div>" +
                '</div>';
        }).join("");

        dom.settingsSourcesList.querySelectorAll("[data-edit-source]").forEach(function (button) {
            button.addEventListener("click", function () {
                startEditingModuleSource(String(button.getAttribute("data-edit-source") || ""));
            });
        });

        dom.settingsSourcesList.querySelectorAll("[data-remove-source]").forEach(function (button) {
            button.addEventListener("click", function () {
                deleteModuleSource(String(button.getAttribute("data-remove-source") || ""));
            });
        });
    }

    function renderCreationPermissionsForm() {
        if (!dom.creationPermissionsUsers || !dom.creationPermissionsProfiles) return;
        renderMultiSelectOptions(
            dom.creationPermissionsUsers,
            state.rightsDirectory.users.map(function (user) {
                return { value: String(user.id), label: user.label + (user.email ? " - " + user.email : "") };
            }),
            (state.moduleSettings.pageCreationPermissions?.userIds || []).map(function (value) { return String(value); })
        );
        renderMultiSelectOptions(
            dom.creationPermissionsProfiles,
            state.rightsDirectory.profiles.map(function (profile) {
                return { value: String(profile), label: String(profile) };
            }),
            state.moduleSettings.pageCreationPermissions?.profileTypes || []
        );
    }

    function renderPagePermissionsForm() {
        if (!dom.pagePermissionsCard || !dom.pagePermissionsUsers || !dom.pagePermissionsProfiles) return;
        const page = getCurrentPage();
        const canManage = canManageCurrentPagePermissions();
        dom.pagePermissionsCard.hidden = !(page && !page.isPlaceholder && canManage);
        if (!page || page.isPlaceholder || !canManage) {
            return;
        }

        if (dom.pagePermissionsDescription) {
            const owner = String(page.ownerDisplayName || page.ownerEmail || "Utilisateur");
            dom.pagePermissionsDescription.textContent = "Page proprietaire : " + owner + ". Sans selection, la page reste visible pour tous les utilisateurs connectes.";
        }

        renderMultiSelectOptions(
            dom.pagePermissionsUsers,
            state.rightsDirectory.users.map(function (user) {
                return { value: String(user.id), label: user.label + (user.email ? " - " + user.email : "") };
            }),
            (page.allowedUserIds || []).map(function (value) { return String(value); })
        );
        renderMultiSelectOptions(
            dom.pagePermissionsProfiles,
            state.rightsDirectory.profiles.map(function (profile) {
                return { value: String(profile), label: String(profile) };
            }),
            page.allowedProfileTypes || []
        );
    }

    function setActiveSettingsTab(tabName) {
        state.activeSettingsTab = String(tabName || "connections");
        syncSettingsTabs();
    }

    function syncSettingsTabs() {
        const availableTabs = [];
        dom.settingsPanels?.forEach(function (panel) {
            const tabName = String(panel.getAttribute("data-settings-panel") || "");
            const hasVisibleContent = !panel.hidden || tabName === "connections" || tabName === "sources" || tabName === "rights";
            if (tabName !== "" && hasVisibleContent) {
                availableTabs.push(tabName);
            }
        });

        if (availableTabs.indexOf(state.activeSettingsTab) === -1) {
            state.activeSettingsTab = availableTabs[0] || "connections";
        }

        dom.settingsTabs?.forEach(function (button) {
            const tabName = String(button.getAttribute("data-settings-tab") || "");
            const isActive = tabName === state.activeSettingsTab;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-selected", isActive ? "true" : "false");
        });

        dom.settingsPanels?.forEach(function (panel) {
            const tabName = String(panel.getAttribute("data-settings-panel") || "");
            panel.hidden = tabName !== state.activeSettingsTab;
        });
    }

    function renderMultiSelectOptions(select, options, selectedValues) {
        if (!select) return;
        const selectedSet = new Set((Array.isArray(selectedValues) ? selectedValues : []).map(function (value) {
            return String(value);
        }));
        select.innerHTML = (Array.isArray(options) ? options : []).map(function (option) {
            const value = String(option.value || "");
            const label = String(option.label || value);
            const selected = selectedSet.has(value) ? " selected" : "";
            return '<option value="' + escapeHtml(value) + '"' + selected + ">" + escapeHtml(label) + "</option>";
        }).join("");
    }

    function getSelectedMultiValues(select) {
        if (!select) return [];
        return Array.from(select.selectedOptions || []).map(function (option) {
            return String(option.value || "");
        }).filter(Boolean);
    }

    function findModuleSourceById(sourceId) {
        const normalizedSourceId = String(sourceId || "");
        if (normalizedSourceId === "") {
            return null;
        }

        const uploadedSource = (state.moduleSettings.uploadedSources || []).find(function (source) {
            return String(source.id || "") === normalizedSourceId;
        });
        if (uploadedSource) {
            return { kind: "uploaded", source: uploadedSource };
        }

        const remoteSource = (state.moduleSettings.remoteSources || []).find(function (source) {
            return String(source.id || "") === normalizedSourceId;
        });
        if (remoteSource) {
            return { kind: "remote", source: remoteSource };
        }

        const apiSource = (state.moduleSettings.apiSources || []).find(function (source) {
            return String(source.id || "") === normalizedSourceId;
        });
        if (apiSource) {
            return { kind: "api", source: apiSource };
        }

        return null;
    }

    function resetEditSourceForm() {
        state.editingModuleSourceId = "";

        if (dom.editSourceForm) {
            dom.editSourceForm.reset();
        }

        if (dom.editSourceId) dom.editSourceId.value = "";
        if (dom.editSourceTitle) dom.editSourceTitle.textContent = "Modifier la source";
        if (dom.editSourceDescription) dom.editSourceDescription.textContent = "Mettez a jour les informations de la source selectionnee.";
        if (dom.editSourceUrlField) dom.editSourceUrlField.hidden = false;
        if (dom.editSourceTokenField) dom.editSourceTokenField.hidden = true;
        if (dom.editSourceInfoField) dom.editSourceInfoField.hidden = true;
        if (dom.editSourceInfo) dom.editSourceInfo.textContent = "";
        if (dom.editSourceUrlHelp) dom.editSourceUrlHelp.textContent = "";
        if (dom.editSourceTokenHelp) dom.editSourceTokenHelp.textContent = "Laissez vide pour conserver le token actuel.";
        if (dom.editSourceSubmit) dom.editSourceSubmit.textContent = "Enregistrer les modifications";
        if (dom.editSourceCard) dom.editSourceCard.hidden = true;
    }

    function startEditingModuleSource(sourceId) {
        const entry = findModuleSourceById(sourceId);
        if (!entry || !dom.editSourceCard) {
            showSettingsFeedback("Source BI introuvable.", "is-error");
            return;
        }

        state.editingModuleSourceId = String(entry.source.id || "");
        if (dom.editSourceId) dom.editSourceId.value = state.editingModuleSourceId;
        if (dom.editSourceLabel) dom.editSourceLabel.value = String(entry.source.label || "");
        if (dom.editSourceUrl) dom.editSourceUrl.value = String(entry.source.url || "");
        if (dom.editSourceToken) dom.editSourceToken.value = "";
        if (dom.editSourceInfo) dom.editSourceInfo.textContent = "";
        if (dom.editSourceInfoField) dom.editSourceInfoField.hidden = true;
        if (dom.editSourceUrlField) dom.editSourceUrlField.hidden = false;
        if (dom.editSourceTokenField) dom.editSourceTokenField.hidden = true;
        if (dom.editSourceUrlHelp) dom.editSourceUrlHelp.textContent = "";
        if (dom.editSourceTokenHelp) dom.editSourceTokenHelp.textContent = "Laissez vide pour conserver le token actuel.";

        if (entry.kind === "uploaded") {
            if (dom.editSourceTitle) dom.editSourceTitle.textContent = "Renommer la source importee";
            if (dom.editSourceDescription) dom.editSourceDescription.textContent = "Vous pouvez modifier le libelle. Pour changer le fichier, importez une nouvelle source.";
            if (dom.editSourceUrlField) dom.editSourceUrlField.hidden = true;
            if (dom.editSourceTokenField) dom.editSourceTokenField.hidden = true;
            if (dom.editSourceInfoField) dom.editSourceInfoField.hidden = false;
            if (dom.editSourceInfo) dom.editSourceInfo.textContent = String(entry.source.fileName || entry.source.path || "Fichier local");
            if (dom.editSourceSubmit) dom.editSourceSubmit.textContent = "Enregistrer le libelle";
        } else if (entry.kind === "remote") {
            if (dom.editSourceTitle) dom.editSourceTitle.textContent = "Modifier l URL SharePoint";
            if (dom.editSourceDescription) dom.editSourceDescription.textContent = "Mettez a jour le libelle ou l URL directe du fichier distant.";
            if (dom.editSourceUrlHelp) dom.editSourceUrlHelp.textContent = "Le lien doit pointer directement vers un fichier CSV, Excel ou JSON.";
            if (dom.editSourceSubmit) dom.editSourceSubmit.textContent = "Enregistrer les modifications";
        } else if (entry.kind === "api") {
            if (dom.editSourceTitle) dom.editSourceTitle.textContent = "Modifier le webservice JSON";
            if (dom.editSourceDescription) dom.editSourceDescription.textContent = "Mettez a jour le libelle, l URL API et, si besoin, le token Bearer.";
            if (dom.editSourceUrlHelp) dom.editSourceUrlHelp.textContent = "Le dashboard appellera cette URL en GET avec un token Bearer.";
            if (dom.editSourceTokenField) dom.editSourceTokenField.hidden = false;
            if (dom.editSourceTokenHelp) dom.editSourceTokenHelp.textContent = entry.source.tokenPreview
                ? "Laissez vide pour conserver le token actuel (" + String(entry.source.tokenPreview || "") + ")."
                : "Laissez vide pour conserver le token actuel.";
            if (dom.editSourceSubmit) dom.editSourceSubmit.textContent = "Enregistrer les modifications";
        }

        dom.editSourceCard.hidden = false;
        dom.editSourceCard.scrollIntoView({ behavior: "smooth", block: "nearest" });
        dom.editSourceLabel?.focus();
    }

    function showSettingsFeedback(message, cssClass) {
        if (!dom.settingsFeedback) return;
        dom.settingsFeedback.hidden = false;
        dom.settingsFeedback.textContent = String(message || "");
        dom.settingsFeedback.classList.remove("is-error", "is-success");
        if (cssClass) {
            dom.settingsFeedback.classList.add(cssClass);
        }
    }

    function clearSettingsFeedback() {
        if (!dom.settingsFeedback) return;
        dom.settingsFeedback.hidden = true;
        dom.settingsFeedback.textContent = "";
        dom.settingsFeedback.classList.remove("is-error", "is-success");
    }

    function bindInlineColorField(trigger, nativeInput, hexInput, key, fallback) {
        if (!trigger || !nativeInput || !hexInput) return;

        const applyValue = function (value) {
            const widget = getSelectedWidget();
            if (!widget) {
                return;
            }
            const normalized = normalizeColorInputValue(value || "");
            if (key === "color") {
                widget.color = normalized;
            } else {
                widget[key] = normalized;
            }
            syncInspectorColorControls(widget);
            refreshWidgetAfterModalEdit();
        };

        trigger.addEventListener("click", function (event) {
            event.preventDefault();
            nativeInput.click();
        });

        nativeInput.addEventListener("input", function () {
            applyValue(String(nativeInput.value || fallback || ""));
        });

        hexInput.addEventListener("input", function () {
            const value = String(hexInput.value || "").trim();
            if (value === "") {
                applyValue("");
                return;
            }
            if (/^#([0-9a-f]{6})$/i.test(value)) {
                applyValue(value);
            }
        });
    }

    function bindColorTrigger(trigger, options) {
        trigger.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleColorPopover(trigger, options);
        });
    }

    function syncColorTrigger(trigger, value) {
        if (!trigger) return;
        const normalized = normalizeColorInputValue(value || "");
        trigger.style.setProperty("--bi-current-color", normalized || "#1f2937");
        trigger.dataset.currentColor = normalized || "";
    }

    function toggleColorPopover(trigger, options) {
        if (state.activeColorPopover && state.activeColorPopover.trigger === trigger) {
            closeColorPopover();
            return;
        }

        closeColorPopover();
        openColorPopover(trigger, options);
    }

    function openColorPopover(trigger, options) {
        const wrapper = trigger.closest(".color-input-wrapper, .bi-modal-color-field");
        if (!wrapper) return;
        const currentColor = normalizeColorInputValue(options.getValue?.() || options.fallback || "");
        const popover = document.createElement("div");
        popover.className = "bi-color-popover";
        const simpleOnly = Boolean(options.simpleOnly);
        popover.innerHTML = '<div class="bi-color-section"><div class="bi-color-section-title">Palette</div><div class="bi-color-swatch-grid">' + palette.map(function (color) {
            const activeClass = currentColor === color ? " is-active" : "";
            return '<button type="button" class="bi-color-swatch' + activeClass + '" data-color-value="' + escapeHtml(color) + '" style="--bi-swatch-color:' + escapeHtml(color) + ';" aria-label="Choisir ' + escapeHtml(color) + '"></button>';
        }).join("") + '</div></div>' +
            (simpleOnly ? "" : '<input type="color" class="bi-color-native-input" value="' + escapeHtml(currentColor || options.fallback || "#1f2937") + '" aria-label="Choisir une couleur"><input type="text" class="bi-color-hex-input" value="' + escapeHtml(currentColor || "") + '" placeholder="#FFFFFF" maxlength="7">') +
            '<button type="button" class="bi-color-reset">Reinitialiser</button>';
        document.body.appendChild(popover);

        const hexInput = popover.querySelector(".bi-color-hex-input");
        const nativeInput = popover.querySelector(".bi-color-native-input");
        const positionPopover = function () {
            const triggerRect = trigger.getBoundingClientRect();
            const top = Math.min(window.innerHeight - popover.offsetHeight - 12, triggerRect.bottom + 8);
            const left = Math.min(window.innerWidth - popover.offsetWidth - 12, Math.max(12, triggerRect.left));
            popover.style.left = Math.max(12, left) + "px";
            popover.style.top = Math.max(12, top) + "px";
        };
        const applyColor = function (value) {
            const normalized = normalizeColorInputValue(value || "");
            syncColorTrigger(trigger, normalized || "");
            options.setValue?.(normalized || "");
            popover.querySelectorAll(".bi-color-swatch").forEach(function (swatch) {
                swatch.classList.toggle("is-active", String(swatch.getAttribute("data-color-value") || "").toLowerCase() === normalized.toLowerCase());
            });
            if (hexInput) {
                hexInput.value = normalized ? normalized.toUpperCase() : "";
            }
            if (nativeInput) {
                nativeInput.value = normalized || options.fallback || "#1f2937";
            }
        };

        popover.querySelectorAll(".bi-color-swatch").forEach(function (swatch) {
            swatch.addEventListener("click", function (event) {
                event.preventDefault();
                applyColor(String(swatch.getAttribute("data-color-value") || ""));
            });
        });

        hexInput?.addEventListener("input", function () {
            const value = String(hexInput.value || "").trim();
            if (value === "") {
                applyColor("");
                return;
            }
            if (/^#([0-9a-f]{6})$/i.test(value)) {
                applyColor(value);
            }
        });

        nativeInput?.addEventListener("input", function () {
            applyColor(String(nativeInput.value || ""));
        });

        popover.querySelector(".bi-color-reset")?.addEventListener("click", function (event) {
            event.preventDefault();
            syncColorTrigger(trigger, "");
            options.setValue?.("");
            if (hexInput) {
                hexInput.value = "";
            }
            if (nativeInput) {
                nativeInput.value = options.fallback || "#1f2937";
            }
            popover.querySelectorAll(".bi-color-swatch").forEach(function (swatch) {
                swatch.classList.remove("is-active");
            });
        });

        positionPopover();
        state.activeColorPopover = { trigger: trigger, popover: popover, reposition: positionPopover };
    }

    function closeColorPopover() {
        if (!state.activeColorPopover) return;
        if (state.activeColorPopover.popover?.parentNode) {
            state.activeColorPopover.popover.parentNode.removeChild(state.activeColorPopover.popover);
        }
        state.activeColorPopover = null;
    }

    function renderPalette() {
        if (!dom.builderTopbar || !dom.widgetPalette) return;
        dom.builderTopbar.hidden = !(canEditCurrentPage() && state.editMode);
        const paletteTones = ["#60a5fa", "#22c55e", "#f59e0b", "#f472b6", "#a78bfa", "#06b6d4", "#f97316", "#84cc16", "#ef4444"];
        dom.widgetPalette.innerHTML = state.widgetCatalog.map(function (widget) {
            const tone = paletteTones[Math.abs(hashCode(String(widget.type || widget.label || "bi")) % paletteTones.length)];
            return '<button type="button" class="bi-palette-button" data-widget-type="' + escapeHtml(widget.type) + '" style="--bi-palette-accent:' + escapeHtml(tone) + ';">' +
                '<span class="bi-palette-icon"><i class="bi ' + escapeHtml(widget.icon || "bi-bar-chart") + '"></i></span>' +
                '<span class="bi-palette-label">' + escapeHtml(widget.label) + '</span>' +
                "</button>";
        }).join("");
        dom.widgetPalette.querySelectorAll("[data-widget-type]").forEach(function (button) {
            button.addEventListener("click", function () {
                addWidget(String(button.getAttribute("data-widget-type") || ""));
            });
        });
    }

    function renderFiltersBar() {
        if (!dom.filtersBar) return;

        const page = getCurrentPage();
        if (!page) {
            dom.filtersBar.hidden = true;
            dom.filtersBar.innerHTML = "";
            return;
        }

        if (!page.filters.length) {
            dom.filtersBar.hidden = true;
            dom.filtersBar.innerHTML = "";
            return;
        }

        const columnOptions = getColumnOptions();
        const isEditMode = canEditCurrentPage() && state.editMode;
        dom.filtersBar.hidden = false;
        dom.filtersBar.innerHTML = page.filters.map(function (filter, index) {
            const valueOptions = buildDistinctValueOptions(filter.column, filter.value, getPageScopedRows(index));
            const selectedColumn = columnOptions.find(function (column) {
                return column.key === filter.column;
            });
            const columnSelect = buildSelectOptions(columnOptions.map(function (column) {
                return { value: column.key, label: column.label };
            }), filter.column);
            const valueSelect = buildSelectOptions(valueOptions, filter.value);
            const readonlyClass = isEditMode ? "" : " is-readonly";
            const columnField = isEditMode
                ? '<select class="stats-select" data-filter-column>' + columnSelect + '</select>'
                : '<div class="bi-filter-label">' + escapeHtml(selectedColumn?.label || humanizeKey(filter.column)) + '</div>';

            return '<div class="bi-filter-chip' + readonlyClass + '" data-filter-index="' + index + '">' +
                columnField +
                '<select class="stats-select" data-filter-value>' + valueSelect + "</select>" +
                (isEditMode ? '<button type="button" class="bi-widget-action bi-filter-remove" data-filter-remove>&times;</button>' : "") +
                "</div>";
        }).join("");

        dom.filtersBar.querySelectorAll("[data-filter-column]").forEach(function (select, index) {
            select.addEventListener("change", function () {
                const page = getCurrentPage();
                const filter = page?.filters[index];
                if (!filter) return;
                filter.column = String(select.value || "");
                filter.value = getDistinctColumnValues(filter.column, getPageScopedRows(index))[0] || "";
                renderAll();
                scheduleSavePreferences();
            });
        });

        dom.filtersBar.querySelectorAll("[data-filter-value]").forEach(function (select, index) {
            select.disabled = false;
            select.addEventListener("change", function () {
                const page = getCurrentPage();
                const filter = page?.filters[index];
                if (!filter) return;
                filter.value = String(select.value || "");
                renderAll();
                scheduleSavePreferences();
            });
        });

        dom.filtersBar.querySelectorAll("[data-filter-remove]").forEach(function (button, index) {
            button.addEventListener("click", function () {
                const page = getCurrentPage();
                if (!page) return;
                page.filters.splice(index, 1);
                renderAll();
                scheduleSavePreferences();
            });
        });
    }

    function getPageScopedRows(excludedFilterIndex) {
        const page = getCurrentPage();
        const rows = Array.isArray(state.dataset?.rows) ? state.dataset.rows : [];
        if (!page || !Array.isArray(page.filters) || !page.filters.length) {
            return rows;
        }

        return rows.filter(function (row) {
            return page.filters.every(function (filter, index) {
                if (index === excludedFilterIndex) {
                    return true;
                }

                const column = String(filter?.column || "").trim();
                const value = String(filter?.value || "").trim();
                if (!column || !value) {
                    return true;
                }

                return normalizeFilterComparableValue(row?.[column]) === normalizeFilterComparableValue(value);
            });
        });
    }

    function renderWidgets() {
        if (!dom.widgetsGrid || !dom.emptyState) return;
        destroyCharts();

        const page = getCurrentPage();
        const widgets = Array.isArray(page?.widgets) ? page.widgets.filter(function (widget) {
            return state.editMode || !widget.hidden;
        }) : [];
        dom.widgetsGrid.innerHTML = "";
        dom.emptyState.hidden = widgets.length > 0;

        if (!widgets.length) {
            dom.emptyState.innerHTML = buildEmptyStateHtml();
            dom.emptyState.hidden = false;
            updateInspectorPreview(null);
            bindEmptyStateActions();
            return;
        }

        widgets.forEach(function (widget, index) {
            const result = computeWidgetResult(widget);
            const card = document.createElement("div");
            card.className = "card card-resizable" + (widget.id === state.selectedWidgetId ? " bi-widget-selected" : "");
            card.setAttribute("data-layout", widget.layout || "4/8");
            card.setAttribute("data-widget-id", widget.id);
            card.setAttribute("data-card-id", widget.id);
            card.setAttribute("data-card-fraction", widget.layout || "4/8");
            card.innerHTML = buildWidgetCardHtml(widget, result, index);
            dom.widgetsGrid.appendChild(card);
            bindWidgetCardEvents(card, widget, index);
            ensureWidgetControls(card, widget);
            renderWidgetBody(card, widget, result);
            applyWidgetCardAppearance(card, widget);
        });

        if (isWidgetModalOpen()) {
            updateInspectorPreview(getSelectedWidget());
        } else {
            destroyPreviewChart();
        }
    }

    function renderWidgetBody(card, widget, result) {
        const body = card.querySelector(".bi-widget-body");
        if (!body) return;
        const chartTextColor = widget.textColor || getComputedStyle(card).color || "#e5e7eb";
        const chartTextSize = normalizeWidgetTextSize(widget.textSize);

        if (result.kind === "empty") {
            body.innerHTML = '<div class="bi-widget-empty">' + escapeHtml(result.message) + "</div>";
            return;
        }

        if (result.kind === "kpi") {
            const valueColor = widget.valueColor || result.color;
            body.innerHTML = '<div class="bi-widget-kpi"><div class="bi-widget-kpi-value" style="color:' + escapeHtml(valueColor) + ';">' + escapeHtml(result.value) + '</div>' + (!widget.hideText ? '<div class="bi-widget-kpi-meta">' + escapeHtml(result.meta) + '</div>' : '') + "</div>";
            return;
        }

        if (result.kind === "table") {
            body.innerHTML = buildTableHtml(result, widget);
            return;
        }

        body.innerHTML = '<div class="bi-widget-chart"><canvas></canvas></div>';
        const canvas = body.querySelector("canvas");
        if (!canvas || typeof Chart === "undefined") {
            return;
        }

        state.charts[widget.id] = createBiChart(canvas, widget, result, chartTextColor, chartTextSize);
    }

    function getBiChartValueLabelsPlugin() {
        return {
            id: "biValueLabels",
            afterDatasetsDraw: function (chart, args, pluginOptions) {
                const options = pluginOptions && typeof pluginOptions === "object" ? pluginOptions : {};
                if (!options.display) {
                    chart.$biValueLabelsRendered = false;
                    return;
                }

                const dataset = chart.data?.datasets?.[0];
                const meta = chart.getDatasetMeta(0);
                const elements = Array.isArray(meta?.data) ? meta.data : [];
                if (!dataset || !elements.length) {
                    chart.$biValueLabelsRendered = false;
                    return;
                }

                const ctx = chart.ctx;
                const chartArea = chart.chartArea || { left: 0, right: chart.width, top: 0, bottom: chart.height };
                const baseColor = String(options.color || "#e5e7eb");
                const fontSize = normalizeWidgetTextSize(options.fontSize, WIDGET_TEXT_SIZE_DEFAULT);
                const chartType = String(options.chartType || chart.config.type || "");
                const isPieLike = chartType === "pie" || chartType === "doughnut";
                const isHorizontal = Boolean(options.horizontal);
                let drawnCount = 0;

                ctx.save();
                ctx.font = "600 " + fontSize + "px Arial";
                ctx.textBaseline = "middle";

                elements.forEach(function (element, index) {
                    const rawValue = Number(dataset.data?.[index] ?? 0);
                    if (!Number.isFinite(rawValue)) {
                        return;
                    }

                    const label = String(options.formatter ? options.formatter(rawValue, index) : rawValue);
                    if (label.trim() === "") {
                        return;
                    }

                    const position = getChartValueLabelPosition(element, chartType, isHorizontal);
                    if (!position) {
                        return;
                    }

                    let x = position.x;
                    let y = position.y;
                    ctx.textAlign = position.align || "center";

                    if (isPieLike) {
                        const segmentColor = Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[index] : dataset.backgroundColor;
                        ctx.fillStyle = getContrastTextColor(segmentColor, baseColor);
                    } else {
                        ctx.fillStyle = baseColor;
                        x = clamp(x, chartArea.left + 8, chartArea.right - 8);
                        y = clamp(y, chartArea.top + 10, chartArea.bottom - 10);
                    }

                    ctx.fillText(label, x, y);
                    drawnCount += 1;
                });

                ctx.restore();
                chart.$biValueLabelsRendered = drawnCount > 0;
            },
        };
    }

    function getChartValueLabelPosition(element, chartType, isHorizontal) {
        if (!element || typeof element.tooltipPosition !== "function") {
            return null;
        }

        const point = element.tooltipPosition();
        if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.y)) {
            return null;
        }

        if (chartType === "pie" || chartType === "doughnut") {
            return {
                x: point.x,
                y: point.y,
                align: "center",
            };
        }

        if (chartType === "line") {
            return {
                x: point.x,
                y: point.y - 14,
                align: "center",
            };
        }

        if (isHorizontal) {
            return {
                x: point.x + 10,
                y: point.y,
                align: "left",
            };
        }

        return {
            x: point.x,
            y: point.y - 14,
            align: "center",
        };
    }

    function getContrastTextColor(backgroundColor, fallbackColor) {
        const color = String(backgroundColor || "").trim();
        const rgb = parseCssColor(color);
        if (!rgb) {
            return fallbackColor || "#ffffff";
        }

        const luminance = ((0.299 * rgb.r) + (0.587 * rgb.g) + (0.114 * rgb.b)) / 255;
        return luminance > 0.62 ? "#111827" : "#ffffff";
    }

    function parseCssColor(value) {
        const color = String(value || "").trim();
        if (/^#([0-9a-f]{6})$/i.test(color)) {
            return {
                r: parseInt(color.slice(1, 3), 16),
                g: parseInt(color.slice(3, 5), 16),
                b: parseInt(color.slice(5, 7), 16),
            };
        }

        const rgbaMatch = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (rgbaMatch) {
            return {
                r: Number(rgbaMatch[1] || 0),
                g: Number(rgbaMatch[2] || 0),
                b: Number(rgbaMatch[3] || 0),
            };
        }

        return null;
    }

    function formatChartValueLabel(value, widget) {
        const absoluteValue = Math.abs(Number(value || 0));
        if (widget?.format === "currency" || widget?.format === "percent") {
            return formatValue(value, widget.format, widget);
        }

        return absoluteValue >= 10000 ? formatCompactNumber(value) : formatValue(value, "", widget);
    }

    function createBiChart(canvas, widget, result, chartTextColor, chartTextSize) {
        return new Chart(canvas, {
            type: result.chartType,
            data: {
                labels: result.labels,
                datasets: [{
                    label: widget.title || "BI",
                    data: result.values,
                    backgroundColor: result.colors,
                    borderColor: result.borderColors,
                    borderWidth: result.chartType === "line" ? 2 : 1,
                    borderRadius: result.chartType === "bar" ? 10 : 0,
                    borderSkipped: result.chartType === "bar" ? false : undefined,
                    barThickness: result.chartType === "bar" ? 18 : undefined,
                    maxBarThickness: result.chartType === "bar" ? 24 : undefined,
                    fill: result.chartType === "line",
                    tension: result.chartType === "line" ? 0.28 : 0,
                }],
            },
            plugins: [getBiChartValueLabelsPlugin()],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: result.horizontal ? "y" : "x",
                layout: {
                    padding: result.chartType === "pie" || result.chartType === "doughnut"
                        ? { top: 10, right: 10, bottom: 10, left: 10 }
                        : {
                            top: 20,
                            right: result.horizontal ? 48 : 16,
                            bottom: 12,
                            left: 16,
                        },
                },
                plugins: {
                    legend: {
                        display: !widget.hideText && (result.chartType !== "bar" || result.labels.length <= 12),
                        position: "bottom",
                        labels: {
                            color: chartTextColor,
                            font: { size: chartTextSize },
                        },
                    },
                    biValueLabels: {
                        display: !widget.hideText,
                        color: chartTextColor,
                        fontSize: Math.max(0, chartTextSize - 1),
                        horizontal: Boolean(result.horizontal),
                        chartType: result.chartType,
                        formatter: function (value) {
                            return formatChartValueLabel(value, widget);
                        },
                    },
                },
                scales: result.chartType === "pie" || result.chartType === "doughnut" ? {} : {
                    x: {
                        display: !widget.hideText,
                        ticks: { color: chartTextColor, display: !widget.hideText, font: { size: chartTextSize } },
                        grid: { display: !widget.hideText, color: "rgba(148, 163, 184, 0.16)" },
                        beginAtZero: result.horizontal,
                    },
                    y: {
                        beginAtZero: true,
                        display: !widget.hideText,
                        ticks: { color: chartTextColor, display: !widget.hideText, font: { size: chartTextSize } },
                        grid: { display: !widget.hideText, color: "rgba(148, 163, 184, 0.16)" },
                    },
                },
            },
        });
    }

    function rerenderWidgetCard(card, widget) {
        if (state.charts[widget.id] && typeof state.charts[widget.id].destroy === "function") {
            state.charts[widget.id].destroy();
            delete state.charts[widget.id];
        }

        renderWidgetBody(card, widget, computeWidgetResult(widget));
        applyWidgetCardAppearance(card, widget);
    }

    function refreshRenderedWidgetCard(widget) {
        if (!dom.widgetsGrid || !widget) {
            return false;
        }

        const page = getCurrentPage();
        const widgets = Array.isArray(page?.widgets) ? page.widgets.filter(function (candidate) {
            return state.editMode || !candidate.hidden;
        }) : [];
        const index = widgets.findIndex(function (candidate) {
            return candidate.id === widget.id;
        });

        if (index < 0) {
            return false;
        }

        const card = Array.from(dom.widgetsGrid.children).find(function (candidate) {
            return candidate.getAttribute("data-widget-id") === widget.id;
        });

        if (!card) {
            return false;
        }

        const result = computeWidgetResult(widget);
        card.className = "card card-resizable" + (widget.id === state.selectedWidgetId ? " bi-widget-selected" : "");
        card.setAttribute("data-layout", widget.layout || "4/8");
        card.setAttribute("data-widget-id", widget.id);
        card.setAttribute("data-card-id", widget.id);
        card.setAttribute("data-card-fraction", widget.layout || "4/8");
        card.innerHTML = buildWidgetCardHtml(widget, result, index);
        bindWidgetCardEvents(card, widget, index);
        ensureWidgetControls(card, widget);
        renderWidgetBody(card, widget, result);
        applyWidgetCardAppearance(card, widget);
        return true;
    }

    function renderInspector() {
        const widget = getSelectedWidget();
        if (!dom.inspectorEmpty || !dom.inspectorForm) return;
        dom.inspectorEmpty.hidden = !!widget;
        dom.inspectorForm.hidden = !widget;
        dom.inspectorHint.textContent = widget ? (widget.title || "Bloc selectionne") : "Selectionnez un composant";

        if (!widget) {
            syncInspectorColorControls(null);
            updateInspectorPreview(null);
            closeModal("widget");
            return;
        }

        const builder = state.builderOptions || buildBuilderOptionsFromDataset(null);
        const columns = Array.isArray(builder.columns) ? builder.columns : [];
        const dataHintMessage = getWidgetConfigurationHint(widget, columns);
        dom.widgetTitle.value = widget.title || "";
        dom.widgetType.innerHTML = buildSelectOptions(state.widgetCatalog.map(function (item) {
            return { value: item.type, label: item.label };
        }), widget.type);
        dom.widgetLayout.innerHTML = buildSelectOptions(builder.layouts || [], widget.layout, "key");
        dom.widgetLayout.disabled = false;
        if (dom.widgetAlignment) dom.widgetAlignment.value = String(widget.alignment || "left");
        if (dom.widgetCardHeight) dom.widgetCardHeight.value = String(widget.cardHeight || 75);
        if (dom.widgetCardHeightValue) dom.widgetCardHeightValue.textContent = String(widget.cardHeight || 75) + " px";
        renderWidgetDataBuilder(widget, columns);
        syncWidgetTextSizeControls(normalizeWidgetTextSize(widget.textSize));
        syncWidgetValueSizeControls(normalizeWidgetValueSize(widget.valueSize));
        if (dom.widgetHideTitle) dom.widgetHideTitle.checked = Boolean(widget.hideTitle);
        if (dom.widgetHideText) dom.widgetHideText.checked = Boolean(widget.hideText);
        if (dom.widgetDataHint) {
            dom.widgetDataHint.hidden = dataHintMessage === "";
            dom.widgetDataHint.textContent = dataHintMessage;
            dom.widgetDataHint.classList.toggle("is-error", dataHintMessage !== "");
        }
        syncInspectorColorControls(widget);
        updateInspectorPreview(widget);
    }

    function syncInspectorColorControls(widget) {
        const syncField = function (trigger, nativeInput, hexInput, value, fallback) {
            const normalized = normalizeColorInputValue(value || "");
            syncColorTrigger(trigger, normalized || "");
            if (nativeInput) {
                nativeInput.value = normalized || fallback;
            }
            if (hexInput) {
                hexInput.value = normalized ? normalized.toUpperCase() : "";
            }
        };

        if (!widget) {
            syncField(dom.widgetChartColor, dom.widgetChartColorInput, dom.widgetChartColorHex, "", "#3b82f6");
            syncField(dom.widgetBgColor, dom.widgetBgColorInput, dom.widgetBgColorHex, "", "#1f2937");
            syncField(dom.widgetTextColor, dom.widgetTextColorInput, dom.widgetTextColorHex, "", "#f8fafc");
            syncField(dom.widgetTitleColor, dom.widgetTitleColorInput, dom.widgetTitleColorHex, "", "#f8fafc");
            syncField(dom.widgetValueColor, dom.widgetValueColorInput, dom.widgetValueColorHex, "", "#60a5fa");
            return;
        }

        syncField(dom.widgetChartColor, dom.widgetChartColorInput, dom.widgetChartColorHex, widget.color || guessColorForWidget(widget), "#3b82f6");
        syncField(dom.widgetBgColor, dom.widgetBgColorInput, dom.widgetBgColorHex, widget.bgColor || "", "#1f2937");
        syncField(dom.widgetTextColor, dom.widgetTextColorInput, dom.widgetTextColorHex, widget.textColor || "", "#f8fafc");
        syncField(dom.widgetTitleColor, dom.widgetTitleColorInput, dom.widgetTitleColorHex, widget.titleColor || "", "#f8fafc");
        syncField(dom.widgetValueColor, dom.widgetValueColorInput, dom.widgetValueColorHex, widget.valueColor || "", "#60a5fa");
    }

    function updateInspectorPreview(widget) {
        if (!dom.widgetPreview) return;
        destroyPreviewChart();

        if (!widget) {
            dom.widgetPreview.innerHTML = '<div class="bi-preview-empty">Selectionnez un bloc pour afficher son apercu.</div>';
            return;
        }

        const result = computeWidgetResult(widget);
        const alignment = String(widget.alignment || "left");
        const textSize = normalizeWidgetTextSize(widget.textSize);
        const valueSize = normalizeWidgetValueSize(widget.valueSize);
        const cardHeight = clamp(parseInt(widget.cardHeight, 10) || 75, 75, 520);
        const title = escapeHtml(widget.title || getWidgetDefinition(widget.type).defaultTitle || "Bloc BI");
        const subtitle = !widget.hideText && result.subtitle ? '<div class="bi-preview-subtitle">' + escapeHtml(result.subtitle) + '</div>' : "";

        dom.widgetPreview.innerHTML = '<div class="bi-preview-card bi-align-' + escapeHtml(alignment) + '" style="--bi-preview-text-size:' + textSize + 'px;--bi-preview-value-size:' + valueSize + 'px;--bi-preview-height:' + cardHeight + 'px;--bi-preview-bg:' + escapeHtml(widget.bgColor || "#1f2937") + ';--bi-preview-text:' + escapeHtml(widget.textColor || "#f8fafc") + ';--bi-preview-title:' + escapeHtml(widget.titleColor || widget.textColor || "#f8fafc") + ';--bi-preview-value:' + escapeHtml(widget.valueColor || widget.color || "#60a5fa") + ';">' + (!widget.hideTitle ? '<div class="bi-preview-title">' + title + '</div>' : '') + subtitle + '<div class="bi-preview-body"></div></div>';

        const previewCard = dom.widgetPreview.querySelector(".bi-preview-card");
        const previewBody = previewCard?.querySelector(".bi-preview-body");
        if (!previewCard || !previewBody) {
            return;
        }

        const chartTextColor = widget.textColor || getComputedStyle(previewCard).color || "#e5e7eb";
        const chartTextSize = normalizeWidgetTextSize(widget.textSize);

        if (result.kind === "empty") {
            previewBody.innerHTML = '<div class="bi-preview-empty">' + escapeHtml(result.message) + '</div>';
            return;
        }

        if (result.kind === "kpi") {
            const valueColor = widget.valueColor || result.color;
            previewBody.innerHTML = '<div class="bi-preview-kpi"><div class="bi-preview-value" style="color:' + escapeHtml(valueColor) + ';">' + escapeHtml(result.value) + '</div>' + (!widget.hideText ? '<div class="bi-preview-meta">' + escapeHtml(result.meta) + '</div>' : '') + '</div>';
            return;
        }

        if (result.kind === "table") {
            previewBody.innerHTML = buildPreviewTableHtml(result, widget);
            return;
        }

        previewBody.innerHTML = '<div class="bi-preview-chart-canvas"><canvas></canvas></div>';
        const canvas = previewBody.querySelector("canvas");
        if (!canvas || typeof Chart === "undefined") {
            return;
        }

        state.previewChart = createBiChart(canvas, widget, result, chartTextColor, chartTextSize);
    }

    function buildPreviewTableHtml(result, widget) {
        if (result.tableVariant === "distribution") {
            return buildPreviewDistributionTableHtml(result, widget);
        }

        if (result.tableVariant === "datatable") {
            return buildDatatableHtml(result, widget, { preview: true, maxRows: 4, maxColumns: 4 });
        }

        const headers = result.columns.slice(0, 4).map(function (column) {
            return "<th>" + escapeHtml(column.label) + "</th>";
        }).join("");
        const rows = result.rows.slice(0, 4).map(function (row) {
            return "<tr>" + result.columns.slice(0, 4).map(function (column) {
                return "<td>" + escapeHtml(String(row[column.key] ?? "")) + "</td>";
            }).join("") + "</tr>";
        }).join("");

        return '<div class="bi-preview-table-wrap"><table class="bi-preview-table-real">' + (!widget.hideText ? '<thead><tr>' + headers + '</tr></thead>' : '') + '<tbody>' + rows + '</tbody></table></div>';
    }

    function buildPreviewDistributionTableHtml(result, widget) {
        return buildDistributionTableMarkup(result, widget, {
            wrapClass: "bi-preview-table-wrap bi-preview-distribution-surface",
            tableClass: "bi-preview-table-real bi-preview-distribution-table",
            maxRows: 4,
        });
    }

    function buildWidgetCardHtml(widget, result, index) {
        const showTitle = !widget.hideTitle;
        const showText = !widget.hideText;
        const subtitle = showText && result.subtitle ? '<div class="bi-widget-subtitle">' + escapeHtml(result.subtitle) + "</div>" : "";
        const headerHtml = showTitle || subtitle !== ""
            ? '<div class="bi-widget-header"><div class="bi-widget-title-wrap">' + (showTitle ? '<h3>' + escapeHtml(widget.title || getWidgetDefinition(widget.type).defaultTitle || "Bloc BI") + '</h3>' : '') + subtitle + '</div></div>'
            : "";
        return headerHtml + '<div class="bi-widget-body" data-widget-index="' + index + '"></div>';
    }

    function bindWidgetCardEvents(card, widget, index) {
        card.addEventListener("click", function (event) {
            const actionButton = event.target.closest("[data-action]");
            if (actionButton) {
                const page = getCurrentPage();
                const realIndex = page ? page.widgets.findIndex(function (candidate) { return candidate.id === widget.id; }) : -1;
                if (realIndex >= 0) {
                    handleWidgetAction(String(actionButton.getAttribute("data-action") || ""), realIndex);
                }
                event.stopPropagation();
                return;
            }

            const sortTh = event.target.closest("[data-sort-col]");
            if (sortTh && widget.type === "datatable") {
                const columnKey = String(sortTh.getAttribute("data-sort-col") || "");
                if (!columnKey) {
                    return;
                }
                if (widget.sortColumn === columnKey) {
                    widget.sortDir = widget.sortDir === "asc" ? "desc" : "asc";
                } else {
                    widget.sortColumn = columnKey;
                    widget.sortDir = "asc";
                }
                rerenderWidgetCard(card, widget);
                scheduleSavePreferences();
                event.stopPropagation();
                return;
            }

            if (event.target.closest && event.target.closest(".stats-resize-button, .card-visibility-toggle, .card-color-picker, .color-input-wrapper, .bi-color-popover, .bi-color-trigger, .bi-color-swatch, .bi-color-native-input, .bi-color-hex-input, .bi-color-reset, input, label, button")) {
                event.stopPropagation();
                return;
            }

            if (!(canEditCurrentPage() && state.editMode)) {
                return;
            }
        });

        card.draggable = canEditCurrentPage() && state.editMode;
        card.classList.toggle("is-editable", canEditCurrentPage() && state.editMode);
        card.addEventListener("dragstart", function (event) {
            if (!(canEditCurrentPage() && state.editMode)) {
                event.preventDefault();
                return;
            }
            if (event.target.closest && event.target.closest(".stats-resize-button, .card-visibility-toggle, .card-color-picker, .color-input-wrapper, input, label, button")) {
                event.preventDefault();
                return;
            }

            state.draggingCard = card;
            state.insertBeforeEl = null;
            window.requestAnimationFrame(function () {
                card.classList.add("is-dragging", "dragging");
                dom.widgetsGrid?.classList.add("is-dragging", "dnd-active");
            });
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = "move";
                event.dataTransfer.dropEffect = "move";
                event.dataTransfer.setData("text/plain", widget.id || "bi-widget");
                event.dataTransfer.setDragImage(transparentDragImage, 0, 0);
            }
        });

        card.addEventListener("dragend", function () {
            cleanupDraggingState();
        });
    }

    function ensureWidgetControls(card, widget) {
        if (!canEditCurrentPage()) {
            return;
        }

        ensureResizeControls(card, widget);
        ensureVisibilityToggle(card, widget);
    }

    function ensureResizeControls(card, widget) {
        const controls = document.createElement("div");
        controls.className = "stats-resize-controls";
        controls.hidden = !(canEditCurrentPage() && state.editMode);
        controls.innerHTML = '<button type="button" class="stats-resize-button" data-action="edit" title="Modifier le bloc"><i class="bi bi-pencil-square"></i></button><button type="button" class="stats-resize-button" data-direction="smaller" title="Reduire">-</button><button type="button" class="stats-resize-button" data-direction="larger" title="Agrandir">+</button><button type="button" class="stats-resize-button" data-action="duplicate" title="Dupliquer"><i class="bi bi-copy"></i></button><button type="button" class="stats-resize-button" data-action="delete" title="Supprimer"><i class="bi bi-trash3"></i></button>';
        card.appendChild(controls);

        controls.querySelectorAll(".stats-resize-button").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!(canEditCurrentPage() && state.editMode)) return;
                const action = String(button.getAttribute("data-action") || "");
                if (action !== "") {
                    const page = getCurrentPage();
                    const index = page?.widgets.findIndex(function (candidate) { return candidate.id === widget.id; }) ?? -1;
                    if (index >= 0) {
                        handleWidgetAction(action, index);
                    }
                    return;
                }
                const current = String(widget.layout || "4/8");
                const next = button.getAttribute("data-direction") === "smaller" ? getPrevFraction(current) : getNextFraction(current);
                setWidgetFraction(card, widget, next);
            });
        });

        updateResizeButtonsState(card, widget);
    }

    function ensureVisibilityToggle(card, widget) {
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "card-visibility-toggle";
        toggle.title = "Afficher ou masquer ce bloc";
        toggle.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!canEditCurrentPage()) return;
            widget.hidden = !widget.hidden;
            card.classList.toggle("card-hidden", Boolean(widget.hidden));
            updateVisibilityIcon(toggle, widget);
            if (state.selectedWidgetId === widget.id) {
                renderInspector();
            }
            scheduleSavePreferences();
        });
        card.appendChild(toggle);
        updateVisibilityIcon(toggle, widget);
    }

    function applyWidgetCardAppearance(card, widget) {
        applyCardFraction(card, widget.layout || "4/8");
        card.classList.toggle("card-hidden", Boolean(widget.hidden));
        card.classList.remove("bi-align-left", "bi-align-center", "bi-align-right");
        card.classList.add("bi-align-" + String(widget.alignment || "left"));
        card.dataset.bgColor = widget.bgColor || "";
        card.dataset.textColor = widget.textColor || "";
        card.style.setProperty("--bi-widget-text-size", String(normalizeWidgetTextSize(widget.textSize)) + "px");
        card.style.setProperty("--bi-widget-value-size", String(normalizeWidgetValueSize(widget.valueSize)) + "px");
        card.style.setProperty("--bi-widget-card-height", String(widget.cardHeight || 75) + "px");
        if (widget.bgColor) {
            card.style.setProperty("--bi-widget-bg", widget.bgColor);
            card.style.setProperty("background", widget.bgColor, "important");
            card.style.setProperty("background-color", widget.bgColor, "important");
            card.style.setProperty("background-image", "none", "important");
        } else {
            card.style.removeProperty("--bi-widget-bg");
            card.style.removeProperty("background");
            card.style.removeProperty("background-color");
            card.style.removeProperty("background-image");
        }
        if (widget.textColor) {
            card.style.setProperty("--bi-widget-text", widget.textColor);
            applyTextColorToCard(card, widget.textColor);
        } else {
            card.style.removeProperty("--bi-widget-text");
            resetTextColorOnCard(card);
        }
        if (widget.titleColor) {
            card.style.setProperty("--bi-widget-title-color", widget.titleColor);
        } else {
            card.style.removeProperty("--bi-widget-title-color");
        }
        if (widget.valueColor) {
            card.style.setProperty("--bi-widget-value-color", widget.valueColor);
        } else {
            card.style.removeProperty("--bi-widget-value-color");
        }
    }

    function handleWidgetAction(action, index) {
        if (!canEditCurrentPage()) return;
        const page = getCurrentPage();
        if (!page) return;
        const widgets = page.widgets;
        syncRenderedWidgetLayoutsFromDom(widgets);
        const widget = widgets[index];
        if (!widget) return;
        let duplicateRenderState = null;
        const viewportState = {
            scrollX: window.scrollX,
            scrollY: window.scrollY,
            gridScrollTop: dom.widgetsGrid ? dom.widgetsGrid.scrollTop : 0,
            gridScrollLeft: dom.widgetsGrid ? dom.widgetsGrid.scrollLeft : 0,
        };

        if (action === "edit") {
            state.selectedWidgetId = widget.id;
            syncSelectedWidgetCardState();
            openModal("widget");
            renderInspector();
            return;
        }

        if (action === "move-left" && index > 0) {
            widgets.splice(index, 1);
            widgets.splice(index - 1, 0, widget);
        }

        if (action === "move-right" && index < widgets.length - 1) {
            widgets.splice(index, 1);
            widgets.splice(index + 1, 0, widget);
        }

        if (action === "duplicate") {
            const duplicate = deepClone(widget);
            const sourceCard = dom.widgetsGrid?.querySelector('[data-widget-id="' + escapeAttribute(widget.id) + '"]');
            const layout = String(widget.layout || sourceCard?.getAttribute("data-card-fraction") || "");
            duplicate.id = createId("widget");
            duplicate.title = String(widget.title || getWidgetDefinition(widget.type).defaultTitle || "Bloc BI");
            duplicate.layout = fractions.indexOf(layout) !== -1 ? layout : defaultLayoutForType(widget.type);
            duplicate.cardHeight = clamp(parseInt(widget.cardHeight, 10) || 75, 75, 520);
            duplicate.alignment = String(widget.alignment || "left");
            duplicate.textSize = normalizeWidgetTextSize(widget.textSize);
            duplicate.valueSize = normalizeWidgetValueSize(widget.valueSize);
            duplicateRenderState = {
                id: duplicate.id,
                layout: duplicate.layout,
                flex: String(sourceCard?.style.flex || ""),
                flexBasis: String(sourceCard?.style.flexBasis || ""),
                minWidth: String(sourceCard?.style.minWidth || ""),
                maxWidth: String(sourceCard?.style.maxWidth || ""),
            };
            widgets.splice(index + 1, 0, duplicate);
            state.selectedWidgetId = duplicate.id;
        }

        if (action === "delete") {
            widgets.splice(index, 1);
            if (state.selectedWidgetId === widget.id) {
                state.selectedWidgetId = widgets[index]?.id || widgets[index - 1]?.id || "";
            }
        }

        renderAll();
        requestAnimationFrame(function () {
            if (dom.widgetsGrid) {
                dom.widgetsGrid.scrollTop = viewportState.gridScrollTop;
                dom.widgetsGrid.scrollLeft = viewportState.gridScrollLeft;
            }
            if (duplicateRenderState && dom.widgetsGrid) {
                const duplicateCard = dom.widgetsGrid.querySelector('[data-widget-id="' + escapeAttribute(duplicateRenderState.id) + '"]');
                if (duplicateCard) {
                    duplicateCard.setAttribute("data-layout", duplicateRenderState.layout);
                    duplicateCard.setAttribute("data-card-fraction", duplicateRenderState.layout);
                    if (duplicateRenderState.flex) {
                        duplicateCard.style.flex = duplicateRenderState.flex;
                    }
                    if (duplicateRenderState.flexBasis) {
                        duplicateCard.style.flexBasis = duplicateRenderState.flexBasis;
                    }
                    if (duplicateRenderState.minWidth) {
                        duplicateCard.style.minWidth = duplicateRenderState.minWidth;
                    }
                    if (duplicateRenderState.maxWidth) {
                        duplicateCard.style.maxWidth = duplicateRenderState.maxWidth;
                    }
                }
            }
            window.scrollTo(viewportState.scrollX, viewportState.scrollY);
        });
        scheduleSavePreferences();
    }

    function syncRenderedWidgetLayoutsFromDom(widgets) {
        if (!dom.widgetsGrid || !Array.isArray(widgets) || !widgets.length) {
            return;
        }

        const layoutsById = {};
        dom.widgetsGrid.querySelectorAll(".card[data-widget-id][data-card-fraction]").forEach(function (card) {
            const widgetId = String(card.getAttribute("data-widget-id") || "");
            const fraction = String(card.getAttribute("data-card-fraction") || "");
            if (widgetId && fractions.indexOf(fraction) !== -1) {
                layoutsById[widgetId] = fraction;
            }
        });

        widgets.forEach(function (widget) {
            const widgetId = String(widget?.id || "");
            if (widgetId && layoutsById[widgetId]) {
                widget.layout = layoutsById[widgetId];
            }
        });
    }

    function reorderWidgetsFromDrop() {
        const page = getCurrentPage();
        if (!page || !state.draggingCard) return;

        const movingId = String(state.draggingCard.getAttribute("data-widget-id") || "");
        if (movingId === "") return;

        const widgets = page.widgets.slice();
        const movingIndex = widgets.findIndex(function (widget) { return widget.id === movingId; });
        if (movingIndex === -1) return;

        const movingWidget = widgets.splice(movingIndex, 1)[0];
        const beforeId = state.insertBeforeEl ? String(state.insertBeforeEl.getAttribute("data-widget-id") || "") : "";
        let targetIndex = beforeId !== "" ? widgets.findIndex(function (widget) { return widget.id === beforeId; }) : -1;
        if (targetIndex < 0) {
            widgets.push(movingWidget);
        } else {
            widgets.splice(targetIndex, 0, movingWidget);
        }

        page.widgets = widgets;
    }

    function setWidgetFraction(card, widget, fraction) {
        widget.layout = fraction;
        card.setAttribute("data-card-fraction", fraction);
        applyCardFraction(card, fraction);
        updateResizeButtonsState(card, widget);
        renderInspector();
        scheduleSavePreferences();
    }

    function addWidget(type) {
        if (!canEditCurrentPage()) return;
        const page = getCurrentPage();
        if (!page) return;
        const definition = getWidgetDefinition(type);
        const widget = {
            id: createId("widget"),
            type: type,
            title: definition.defaultTitle || definition.label || "Bloc BI",
            layout: defaultLayoutForType(type),
            dimensionColumn: "",
            valueColumn: "",
            filterColumn: "",
            filterValue: "",
            percentageBase: type === "percentage" ? "group_share" : "",
            targetGoal: "",
            aggregation: type === "table" || type === "distribution-table" ? "count" : "sum",
            displayMode: "chart",
            format: "",
            color: "",
            bgColor: "",
            textColor: "",
            titleColor: "",
            valueColor: "",
            alignment: "left",
            textSize: 15,
            valueSize: type === "kpi" || type === "counter" || type === "percentage" ? 48 : 42,
            cardHeight: type === "table" || type === "distribution-table" ? 320 : (type === "datatable" ? 380 : (type === "kpi" || type === "counter" || type === "percentage" ? 240 : 300)),
            hideTitle: false,
            hideText: false,
            hidden: false,
            maxItems: type === "table" || type === "distribution-table" ? 10 : (type === "datatable" ? 20 : 8),
            tableColumns: [],
            tableColumnStyles: [],
            tableStyles: normalizeDatatableStyleConfig({}),
            sortColumn: "",
            sortDir: "asc",
        };
        applyWidgetDefaults(widget, false);
        page.widgets.push(widget);
        state.selectedWidgetId = widget.id;
        renderAll();
        scheduleSavePreferences();
    }

    function applyWidgetDefaults(widget, preserveTitle) {
        const columns = getColumnOptions();
        const numeric = columns.find(function (column) { return column.type === "number"; });
        const dateColumn = columns.find(function (column) { return column.type === "date"; });
        const dimension = columns.find(function (column) { return column.type === "string" || column.type === "boolean"; });
        const primaryDimension = dimension?.key || dateColumn?.key || "";

        if (!preserveTitle && (!widget.title || widget.title.trim() === "")) {
            widget.title = getWidgetDefinition(widget.type).defaultTitle || "Bloc BI";
        }

        if (widget.type === "line") {
            widget.dimensionColumn = widget.dimensionColumn || dateColumn?.key || primaryDimension;
            widget.valueColumn = widget.valueColumn || "";
            widget.aggregation = widget.valueColumn ? "sum" : "count";
        } else if (widget.type === "bar" || widget.type === "bar-horizontal" || widget.type === "pie" || widget.type === "doughnut") {
            widget.dimensionColumn = widget.dimensionColumn || primaryDimension;
            widget.valueColumn = widget.valueColumn || "";
            widget.aggregation = widget.valueColumn ? "sum" : "count";
        } else if (widget.type === "histogram") {
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.aggregation = "count";
            widget.format = "";
        } else if (widget.type === "counter") {
            widget.valueColumn = widget.valueColumn || "";
            widget.aggregation = widget.valueColumn
                ? getCompatibleMeasureAggregation(widget.aggregation, "sum")
                : "count";
        } else if (widget.type === "kpi") {
            widget.valueColumn = widget.valueColumn || "";
            widget.aggregation = widget.valueColumn ? "sum" : "count";
        } else if (widget.type === "table") {
            widget.dimensionColumn = widget.dimensionColumn || primaryDimension;
            widget.valueColumn = widget.valueColumn || "";
            widget.aggregation = widget.valueColumn
                ? getCompatibleMeasureAggregation(widget.aggregation, "sum")
                : "count";
        } else if (widget.type === "distribution-table") {
            widget.dimensionColumn = widget.dimensionColumn || primaryDimension;
            widget.valueColumn = "";
            widget.aggregation = "count";
            widget.format = "";
        } else if (widget.type === "datatable") {
            widget.dimensionColumn = "";
            widget.valueColumn = "";
            widget.aggregation = "count";
            widget.format = "";
            widget.tableColumns = Array.isArray(widget.tableColumns) && widget.tableColumns.length
                ? widget.tableColumns
                : columns.slice(0, Math.min(columns.length, 6)).map(function (column) { return column.key; });
        } else if (widget.type === "percentage") {
            widget.dimensionColumn = widget.dimensionColumn || primaryDimension;
            widget.valueColumn = widget.valueColumn || "";
            widget.aggregation = "percentage";
            widget.percentageBase = "group_share";
            widget.format = "percent";
        } else {
            widget.valueColumn = widget.valueColumn || "";
            widget.dimensionColumn = widget.dimensionColumn || primaryDimension;
            widget.aggregation = widget.valueColumn ? "sum" : "count";
        }

        ensureWidgetDataModel(widget);

        if (widget.type === "table") {
            widget.rowDimensions = [widget.rowDimensions[0] || widget.dimensionColumn || dimension?.key || ""];
            widget.columnDimensions = [];
        } else {
            widget.chartDimensions = [widget.chartDimensions[0] || widget.dimensionColumn || dateColumn?.key || dimension?.key || ""];
            widget.rowDimensions = [];
            widget.columnDimensions = [];
        }

        widget.measures = [createMeasureEntry(Object.assign({}, widget.measures[0] || {}, {
            column: widget.valueColumn || widget.measures[0]?.column || "",
            aggregation: widget.type === "percentage"
                ? String(widget.measures[0]?.aggregation || (widget.valueColumn ? "sum" : "count"))
                : String(widget.aggregation && widget.aggregation !== "percentage"
                    ? widget.aggregation
                    : (widget.measures[0]?.aggregation || defaultMeasureAggregationForWidget(widget))),
        }))];
        widget.counterItems = [];
        widget.filterColumn = "";
        widget.filterValue = "";
        widget.targetGoal = "";
        widget.percentageBase = widget.type === "percentage" ? "group_share" : "";
        syncWidgetFormatWithMeasure(widget, widget.measures[0]);

        if (!widget.color) {
            widget.color = guessColorForWidget(widget);
        }

        synchronizeLegacyWidgetFields(widget);
    }

    function widgetNeedsAutoDefaults(widget) {
        ensureWidgetDataModel(widget);
        synchronizeLegacyWidgetFields(widget);

        const type = String(widget?.type || "bar");
        const dimensionSelected = String(widget.chartDimensions?.[0] || widget.dimensionColumn || "").trim() !== "";
        const rowSelected = (Array.isArray(widget.rowDimensions) ? widget.rowDimensions : []).some(function (value) {
            return String(value || "").trim() !== "";
        });
        const measure = getPrimaryMeasure(widget);
        const measureColumnSelected = String(measure?.column || widget.valueColumn || "").trim() !== "";

        if (type === "histogram") {
            return !measureColumnSelected;
        }

        if (type === "table") {
            return !rowSelected;
        }

        if (type === "datatable") {
            return !(Array.isArray(widget.tableColumns) && widget.tableColumns.length);
        }

        if (type === "distribution-table") {
            return !dimensionSelected;
        }

        if (type === "percentage") {
            return !dimensionSelected;
        }

        if (["bar", "bar-horizontal", "pie", "doughnut", "line"].indexOf(type) !== -1) {
            return !dimensionSelected;
        }

        return false;
    }

    function hydrateCurrentPageWidgetsForDataset() {
        const page = getCurrentPage();
        const columns = getColumnOptions();
        if (!page || !Array.isArray(page.widgets) || columns.length === 0) {
            return false;
        }

        let hydrated = false;
        page.widgets.forEach(function (widget) {
            if (!widgetNeedsAutoDefaults(widget)) {
                return;
            }

            applyWidgetDefaults(widget, true);
            hydrated = true;
        });

        return hydrated;
    }

    function computeWidgetResult(widget) {
        ensureWidgetDataModel(widget);
        synchronizeLegacyWidgetFields(widget);

        const rows = getFilteredRows();
        if (!rows.length) {
            return { kind: "empty", message: "Aucune donnee disponible pour ce composant." };
        }

        const widgetFilters = getWidgetBuilderFilters(widget);
        const filterDescription = describeFilterCollection(widgetFilters);
        const sourceRows = applyFilterCollection(rows, widgetFilters);
        if (!sourceRows.length) {
            return {
                kind: "empty",
                message: filterDescription
                    ? "Aucune donnee ne correspond aux filtres selectionnes."
                    : "Aucune donnee disponible pour ce composant.",
            };
        }

        const layout = widget.layout || "4/8";
        const title = widget.title || getWidgetDefinition(widget.type).defaultTitle || "Bloc BI";
        const maxItems = clamp(parseInt(widget.maxItems, 10) || 8, 3, 20);
        const color = widget.color || guessColorForWidget(widget);

        if (widget.type === "counter") {
            return computeCounterWidgetResult(widget, sourceRows, title, color, layout, filterDescription);
        }

        if (widget.type === "kpi") {
            const measure = getPrimaryMeasure(widget);
            const measureError = getMeasureConfigurationError(measure, "Selectionnez une valeur principale.");
            if (measureError) {
                return { kind: "empty", message: measureError };
            }
            const aggregate = aggregateRowsForMeasure(sourceRows, measure);

            return {
                kind: "kpi",
                title: title,
                value: formatValue(aggregate.value, widget.format, Object.assign({}, widget, { aggregation: measure.aggregation })),
                meta: buildWidgetAggregateMeta(Object.assign({}, widget, { aggregation: measure.aggregation, valueColumn: measure.column }), aggregate, sourceRows.length, filterDescription),
                color: color,
                subtitle: layout === "2/8" ? "" : "Vue synthetique",
            };
        }

        if (widget.type === "percentage") {
            const percentageDimension = String(widget.chartDimensions[0] || "");
            const percentageMeasure = getPrimaryMeasure(widget);
            const percentageMeasureError = getMeasureConfigurationError(percentageMeasure, "Selectionnez une mesure.");
            if (percentageDimension === "") {
                return { kind: "empty", message: "Selectionnez une colonne de categorie pour calculer le pourcentage." };
            }
            if (percentageMeasureError) {
                return { kind: "empty", message: percentageMeasureError };
            }
            const percent = computePercentage(sourceRows, widget);
            if (percent.error) {
                return { kind: "empty", message: percent.error };
            }
            return {
                kind: "kpi",
                title: title,
                value: formatValue(percent.value, "percent", widget),
                meta: appendFilterDescription(percent.meta, filterDescription),
                color: color,
                subtitle: layout === "2/8" ? "" : "Vue synthese",
            };
        }

        if (widget.type === "distribution-table") {
            return buildDistributionTableResult(sourceRows, widget, filterDescription, maxItems, color);
        }

        if (widget.type === "datatable") {
            return buildDatatableResult(sourceRows, widget, filterDescription);
        }

        if (widget.type === "table") {
            return buildPivotTableResult(sourceRows, widget, filterDescription, maxItems);
        }

        if (widget.type === "histogram") {
            const histogramMeasure = getPrimaryMeasure(widget);
            const histogramError = getMeasureConfigurationError(histogramMeasure, "Choisissez une colonne numerique pour l histogramme.");
            if (histogramError) {
                return { kind: "empty", message: histogramError };
            }
            const histogram = buildHistogram(sourceRows, histogramMeasure.column, maxItems, color);
            if (!histogram.labels.length) {
                return { kind: "empty", message: "Choisissez une colonne numerique pour l histogramme." };
            }
            histogram.subtitle = appendFilterDescription(histogram.subtitle, filterDescription);
            return histogram;
        }

        const dimensionColumn = String(widget.chartDimensions[0] || "");
        const measure = getPrimaryMeasure(widget);
        const measureError = getMeasureConfigurationError(measure, "Configurez la valeur du graphique.");
        if (measureError) {
            return { kind: "empty", message: measureError };
        }
        if (dimensionColumn === "") {
            return { kind: "empty", message: "Choisissez une colonne pour l axe X." };
        }
        const grouped = groupRows(sourceRows, dimensionColumn, measure.column, measure.aggregation, maxItems, measure.matchValue);
        if (!grouped.labels.length) {
            return { kind: "empty", message: "Configurez l axe X et la mesure du graphique." };
        }

        return {
            kind: "chart",
            chartType: widget.type === "doughnut" ? "doughnut" : (widget.type === "bar-horizontal" ? "bar" : widget.type),
            horizontal: widget.type === "bar-horizontal",
            labels: grouped.labels,
            values: grouped.values,
            colors: grouped.labels.map(function (_, index) {
                return index === 0 ? color : palette[index % palette.length];
            }),
            borderColors: grouped.labels.map(function (_, index) {
                return index === 0 ? color : palette[index % palette.length];
            }),
            subtitle: appendFilterDescription(grouped.subtitle, filterDescription),
        };
    }

    function getPrimaryMeasure(widget) {
        ensureWidgetDataModel(widget);

        if (Array.isArray(widget.measures) && widget.measures.length) {
            return widget.measures[0];
        }

        return createMeasureEntry({ aggregation: defaultMeasureAggregationForWidget(widget) });
    }

    function getActiveFilters(filters) {
        return (Array.isArray(filters) ? filters : []).filter(function (filter) {
            const hasInputValue = String(filter?.inputMode || "select") === "input"
                ? String(filter?.value || "").trim() !== ""
                : normalizeFilterSelectionValues(filter?.values, filter?.value).length > 0;
            return String(filter?.column || "").trim() !== "" && hasInputValue;
        });
    }

    function applyFilterCollection(rows, filters) {
        const activeFilters = getActiveFilters(filters);
        if (!activeFilters.length) {
            return rows;
        }

        return rows.filter(function (row) {
            return activeFilters.every(function (filter) {
                const leftValue = normalizeFilterComparableValue(row[filter.column]);
                const selectedValues = String(filter?.inputMode || "select") === "input"
                    ? [String(filter.value || "")]
                    : normalizeFilterSelectionValues(filter.values, filter.value);

                return selectedValues.some(function (selectedValue) {
                    const rightValue = normalizeFilterComparableValue(selectedValue);
                    if (String(filter.operator || "equals") === "contains") {
                        return leftValue.indexOf(rightValue) !== -1;
                    }
                    return leftValue === rightValue;
                });
            });
        });
    }

    function describeFilterCollection(filters) {
        const activeFilters = getActiveFilters(filters);
        if (!activeFilters.length) {
            return "";
        }

        return activeFilters.map(function (filter) {
            const values = String(filter?.inputMode || "select") === "input"
                ? [String(filter.value || "")]
                : normalizeFilterSelectionValues(filter.values, filter.value);
            return humanizeKey(filter.column)
                + (String(filter.operator || "equals") === "contains" ? " contient " : " = ")
                + values.join(", ");
        }).join(" | ");
    }

    function aggregateRowsForMeasure(rows, measure) {
        return aggregateRows(
            rows,
            String(measure?.aggregation || "count"),
            String(measure?.column || ""),
            String(measure?.matchValue || ""),
        );
    }

    function measureNeedsColumn(measure) {
        const aggregation = String(measure?.aggregation || "count");
        return aggregation === "sum" || aggregation === "avg";
    }

    function getMeasureConfigurationError(measure, emptyMessage) {
        if (!measure) {
            return emptyMessage || "Configurez une mesure.";
        }

        if (measureNeedsColumn(measure) && String(measure.column || "").trim() === "") {
            return "Selectionnez une colonne pour ce calcul.";
        }

        return "";
    }

    function computeCounterWidgetResult(widget, sourceRows, title, color, layout, filterDescription) {
        const measure = getPrimaryMeasure(widget);
        const configError = getMeasureConfigurationError(measure, "Choisissez ce qu il faut compter.");
        if (configError) {
            return { kind: "empty", message: configError };
        }

        const aggregate = aggregateRowsForMeasure(sourceRows, measure);

        return {
            kind: "kpi",
            title: title,
            value: formatValue(aggregate.value, widget.format, Object.assign({}, widget, { aggregation: measure.aggregation })),
            meta: buildWidgetAggregateMeta(Object.assign({}, widget, { aggregation: measure.aggregation, valueColumn: measure.column }), aggregate, sourceRows.length, filterDescription),
            color: color,
            subtitle: layout === "2/8" ? "" : "Compteur",
        };
    }

    function buildHistogram(rows, valueColumn, bucketsCount, color) {
        const values = rows.map(function (row) {
            return toNumber(row[valueColumn]);
        }).filter(function (value) {
            return value !== null;
        });

        if (!values.length) {
            return { labels: [] };
        }

        const min = Math.min.apply(Math, values);
        const max = Math.max.apply(Math, values);
        const bucketSize = max === min ? 1 : (max - min) / bucketsCount;
        const buckets = new Array(bucketsCount).fill(0);
        const labels = [];

        for (let index = 0; index < bucketsCount; index += 1) {
            const start = min + (bucketSize * index);
            const end = index === bucketsCount - 1 ? max : start + bucketSize;
            labels.push(formatCompactNumber(start) + " - " + formatCompactNumber(end));
        }

        values.forEach(function (value) {
            const bucketIndex = bucketSize === 0 ? 0 : Math.min(bucketsCount - 1, Math.floor((value - min) / bucketSize));
            buckets[bucketIndex] += 1;
        });

        return {
            kind: "chart",
            chartType: "bar",
            labels: labels,
            values: buckets,
            colors: labels.map(function (_, index) { return index === 0 ? color : palette[index % palette.length]; }),
            borderColors: labels.map(function (_, index) { return index === 0 ? color : palette[index % palette.length]; }),
            subtitle: "Distribution de " + (valueColumn || "la valeur"),
        };
    }

    function groupRows(rows, dimensionColumn, valueColumn, aggregation, maxItems, matchValue) {
        const groups = new Map();
        rows.forEach(function (row) {
            const label = String(dimensionColumn ? (row[dimensionColumn] || "Non renseigne") : "Ensemble").trim() || "Non renseigne";
            if (!groups.has(label)) {
                groups.set(label, []);
            }
            groups.get(label).push(row);
        });

        const effectiveAggregation = aggregation === "percentage"
            ? (String(valueColumn || "").trim() === "" ? "count" : "sum")
            : aggregation;

        const items = Array.from(groups.entries()).map(function (entry) {
            return {
                label: entry[0],
                value: aggregateRows(entry[1], effectiveAggregation, valueColumn, matchValue).raw,
            };
        }).sort(function (left, right) {
            return Number(right.value || 0) - Number(left.value || 0);
        }).slice(0, maxItems);

        if (aggregation === "percentage") {
            const total = items.reduce(function (carry, item) { return carry + Number(item.value || 0); }, 0);
            items.forEach(function (item) {
                item.value = total > 0 ? (Number(item.value || 0) / total) * 100 : 0;
            });
        }

        return {
            labels: items.map(function (item) { return item.label; }),
            values: items.map(function (item) { return Number(item.value || 0); }),
            subtitle: buildMeasureLabel({
                aggregation: aggregation,
                column: valueColumn,
                matchValue: matchValue,
            }) + (dimensionColumn ? " par " + humanizeKey(dimensionColumn) : ""),
        };
    }

    function aggregateRows(rows, aggregation, valueColumn, matchValue) {
        if (aggregation === "count") {
            const columnKey = String(valueColumn || "").trim();
            if (columnKey === "") {
                return { raw: rows.length, value: rows.length, meta: rows.length + " lignes source" };
            }

            const targetValue = String(matchValue || "").trim();
            const countedRows = rows.filter(function (row) {
                const value = row[columnKey];
                if (targetValue !== "") {
                    return normalizeFilterComparableValue(value) === normalizeFilterComparableValue(targetValue);
                }

                if (value === null || value === undefined) {
                    return false;
                }

                if (typeof value === "number") {
                    return Number.isFinite(value);
                }

                return String(value).trim() !== "";
            }).length;

            return {
                raw: countedRows,
                value: countedRows,
                meta: targetValue !== ""
                    ? countedRows + ' lignes ou "' + targetValue + '" est present dans ' + humanizeKey(columnKey)
                    : countedRows + " valeurs renseignees dans " + humanizeKey(columnKey),
            };
        }

        if (!valueColumn) {
            return { raw: 0, value: 0, meta: "Selectionnez une colonne numerique" };
        }

        const values = rows.map(function (row) { return toNumber(row[valueColumn]); }).filter(function (value) { return value !== null; });
        if (!values.length) {
            return { raw: 0, value: 0, meta: "Aucune valeur numerique disponible" };
        }

        if (aggregation === "avg") {
            const avg = values.reduce(function (carry, value) { return carry + value; }, 0) / values.length;
            return { raw: avg, value: avg, meta: "Moyenne sur " + values.length + " lignes" };
        }

        const sum = values.reduce(function (carry, value) { return carry + value; }, 0);
        return { raw: sum, value: sum, meta: "Total sur " + values.length + " lignes" };
    }

    function computePercentage(rows, widget) {
        const dimensionColumn = String(widget.chartDimensions[0] || "");
        const measure = getPrimaryMeasure(widget);
        const valueColumn = String(measure.column || "");
        const aggregateMode = String(measure.aggregation || (valueColumn ? "sum" : "count"));

        const allGroups = groupRows(rows, dimensionColumn, valueColumn, aggregateMode, Math.max(rows.length, 50), measure.matchValue);
        const total = allGroups.values.reduce(function (carry, value) {
            return carry + Number(value || 0);
        }, 0);
        const topValue = Number(allGroups.values[0] || 0);
        const topLabel = allGroups.labels[0] || "Categorie";

        if (!allGroups.labels.length || total <= 0) {
            return { error: "Impossible de calculer un pourcentage avec les champs selectionnes.", value: 0, meta: "" };
        }

        return {
            value: (topValue / total) * 100,
            meta: topLabel + " represente " + formatCompactNumber(topValue) + " sur " + formatCompactNumber(total),
        };
    }

    function buildDistributionTableResult(rows, widget, filterDescription, maxItems, accentColor) {
        const dimensionColumn = String(widget.chartDimensions[0] || "");
        if (dimensionColumn === "") {
            return { kind: "empty", message: "Choisissez une colonne pour afficher la repartition detaillee." };
        }

        const groups = new Map();
        rows.forEach(function (row) {
            const label = String(row[dimensionColumn] ?? "").trim() || "Non renseigne";
            groups.set(label, Number(groups.get(label) || 0) + 1);
        });

        const total = rows.length;
        const groupedRows = Array.from(groups.entries()).map(function (entry) {
            return {
                label: entry[0],
                count: Number(entry[1] || 0),
            };
        }).sort(function (left, right) {
            return right.count - left.count;
        });

        if (!groupedRows.length) {
            return { kind: "empty", message: "Aucune valeur exploitable n est disponible pour cette colonne." };
        }

        const visibleRows = groupedRows.slice(0, maxItems);
        const maxCount = Math.max.apply(null, visibleRows.map(function (item) {
            return Number(item.count || 0);
        }));

        return {
            kind: "table",
            tableVariant: "distribution",
            dimensionLabel: getColumnLabel(dimensionColumn),
            rows: visibleRows.map(function (item, index) {
                const count = Number(item.count || 0);
                const percentage = total > 0 ? (count / total) * 100 : 0;
                const color = index === 0 ? accentColor : palette[index % palette.length];

                return {
                    label: item.label,
                    count: count,
                    percentage: percentage,
                    width: maxCount > 0 ? (count / maxCount) * 100 : 0,
                    color: color,
                };
            }),
            totalCount: total,
            totalRows: total,
            truncated: groupedRows.length > visibleRows.length,
            subtitle: appendFilterDescription(total + " lignes analysees", filterDescription),
        };
    }

    function buildPivotTableResult(rows, widget, filterDescription, maxItems) {
        const rowDimensions = (Array.isArray(widget.rowDimensions) ? widget.rowDimensions : []).filter(Boolean);
        const rawMeasures = Array.isArray(widget.measures) && widget.measures.length ? widget.measures : [createMeasureEntry({ aggregation: "count" })];
        const measureError = getMeasureConfigurationError(rawMeasures[0], "");
        if (measureError) {
            return { kind: "empty", message: measureError };
        }

        const measures = rawMeasures
            .filter(function (measure) { return String(measure.column || "").trim() !== "" || String(measure.aggregation || "") === "count"; });

        if (!rowDimensions.length) {
            return { kind: "empty", message: "Choisissez une colonne pour les lignes du tableau." };
        }

        const effectiveMeasures = measures.length ? measures : [createMeasureEntry({ aggregation: "count" })];
        const rowGroups = new Map();

        rows.forEach(function (row) {
            const rowKey = buildDimensionKey(row, rowDimensions) || "__all__";
            const rowValues = buildDimensionValueList(row, rowDimensions);

            if (!rowGroups.has(rowKey)) {
                rowGroups.set(rowKey, { rowValues: rowValues, cells: new Map() });
            }

            effectiveMeasures.forEach(function (measure) {
                const cellKey = "__all__::" + measure.id;
                const targetRows = rowGroups.get(rowKey).cells.get(cellKey) || [];
                targetRows.push(row);
                rowGroups.get(rowKey).cells.set(cellKey, targetRows);
            });
        });

        const rowHeaders = rowDimensions.map(function (dimension) {
            return { key: "row:" + dimension, label: humanizeKey(dimension) };
        });

        const dynamicColumns = [];
        effectiveMeasures.forEach(function (measure) {
            dynamicColumns.push({
                key: "cell:__all__::" + measure.id,
                cellKey: "__all__::" + measure.id,
                label: buildMeasureLabel(measure),
                measure: measure,
            });
        });

        const resultRows = Array.from(rowGroups.values()).map(function (group) {
            const rowData = {};
            rowDimensions.forEach(function (dimension, index) {
                rowData["row:" + dimension] = String(group.rowValues[index]?.value || "Ensemble");
            });
            dynamicColumns.forEach(function (column) {
                const cellRows = group.cells.get(column.cellKey) || [];
                const aggregate = aggregateRowsForMeasure(cellRows, column.measure);
                rowData[column.key] = formatValue(aggregate.value, widget.format, Object.assign({}, widget, { aggregation: column.measure.aggregation }));
            });
            return rowData;
        }).slice(0, maxItems);

        const columns = rowHeaders.concat(dynamicColumns.map(function (column) {
            return { key: column.key, label: column.label };
        }));

        return {
            kind: "table",
            columns: columns,
            rows: resultRows,
            subtitle: appendFilterDescription(rows.length + " lignes analysees", filterDescription),
        };
    }

    function getVisibleDatatableColumns(widget, rows) {
        const datasetColumns = Array.isArray(state.builderOptions?.columns) ? state.builderOptions.columns : [];
        const configuredKeys = Array.isArray(widget.tableColumns) ? widget.tableColumns : [];
        const visibleColumns = configuredKeys.map(function (key) {
            return datasetColumns.find(function (column) {
                return String(column.key || "") === String(key || "");
            }) || { key: String(key || ""), label: getColumnLabel(key) };
        }).filter(function (column) {
            return String(column?.key || "").trim() !== "";
        });

        if (visibleColumns.length) {
            return visibleColumns;
        }

        if (configuredKeys.length === 0) {
            return [];
        }

        const firstRow = rows[0] && typeof rows[0] === "object" ? rows[0] : null;
        return firstRow
            ? Object.keys(firstRow).map(function (key) {
                return { key: key, label: humanizeKey(key) };
            })
            : [];
    }

    function buildDatatableResult(rows, widget, filterDescription) {
        const visibleColumns = getVisibleDatatableColumns(widget, rows);
        if (!visibleColumns.length) {
            return { kind: "empty", message: "Selectionnez au moins une colonne a afficher dans le tableau personnalise." };
        }

        const sortColumn = String(widget.sortColumn || "");
        const sortDir = String(widget.sortDir || "asc") === "desc" ? "desc" : "asc";
        const maxItems = clamp(parseInt(widget.maxItems, 10) || 20, 5, 100);
        const sortedRows = rows.slice();

        if (sortColumn) {
            sortedRows.sort(function (left, right) {
                const leftValue = String(left?.[sortColumn] ?? "");
                const rightValue = String(right?.[sortColumn] ?? "");
                const numericLeft = toNumber(leftValue);
                const numericRight = toNumber(rightValue);
                let comparison = 0;

                if (numericLeft !== null && numericRight !== null) {
                    comparison = numericLeft - numericRight;
                } else {
                    comparison = localeSort(leftValue, rightValue);
                }

                return sortDir === "desc" ? comparison * -1 : comparison;
            });
        }

        const resultRows = sortedRows.slice(0, maxItems).map(function (row) {
            const rowData = {};
            visibleColumns.forEach(function (column) {
                rowData[column.key] = row?.[column.key] ?? "";
            });
            return rowData;
        });

        return {
            kind: "table",
            tableVariant: "datatable",
            columns: visibleColumns.map(function (column) {
                return { key: String(column.key || ""), label: String(column.label || humanizeKey(column.key)) };
            }),
            rows: resultRows,
            sourceRows: sortedRows.slice(0, maxItems),
            sortColumn: sortColumn,
            sortDir: sortDir,
            subtitle: appendFilterDescription(rows.length + " lignes disponibles", filterDescription),
        };
    }

    function doesRowMatchFilter(row, filter) {
        const column = String(filter?.column || "");
        const selectedValues = String(filter?.inputMode || "select") === "input"
            ? [String(filter?.value || "")]
            : normalizeFilterSelectionValues(filter?.values, filter?.value);
        if (!column || !selectedValues.length) {
            return false;
        }

        const leftValue = normalizeFilterComparableValue(row?.[column]);
        return selectedValues.some(function (value) {
            const rightValue = normalizeFilterComparableValue(value);
            if (String(filter?.operator || "equals") === "contains") {
                return leftValue.indexOf(rightValue) !== -1;
            }

            return leftValue === rightValue;
        });
    }

    function getMatchingFilterValueStyles(filter, row) {
        if (String(filter?.inputMode || "select") === "input") {
            return [createFilterValueStyleEntry({
                value: String(filter?.value || ""),
                styleTarget: filter?.styleTarget || "none",
                bgColor: filter?.bgColor || "",
                textColor: filter?.textColor || "",
            })];
        }

        const entries = normalizeFilterValueStyles(filter?.valueStyles, filter?.values, filter);
        const leftValue = normalizeFilterComparableValue(row?.[filter?.column]);
        return entries.filter(function (entry) {
            const rightValue = normalizeFilterComparableValue(entry.value);
            if (!rightValue) {
                return false;
            }

            if (String(filter?.operator || "equals") === "contains") {
                return leftValue.indexOf(rightValue) !== -1;
            }

            return leftValue === rightValue;
        });
    }

    function buildInlineStyle(styleMap) {
        return Object.keys(styleMap || {}).map(function (key) {
            return styleMap[key] ? key + ":" + styleMap[key] : "";
        }).filter(Boolean).join(";");
    }

    function getDatatableConditionalStyles(widget, row, columnKey) {
        const rowStyle = {};
        const cellStyle = {};
        const filters = getWidgetBuilderFilters(widget);

        filters.forEach(function (filter) {
            if (!doesRowMatchFilter(row, filter)) {
                return;
            }

            getMatchingFilterValueStyles(filter, row).forEach(function (styleEntry) {
                if (String(styleEntry.styleTarget || "none") === "row") {
                    if (styleEntry.bgColor) {
                        rowStyle.background = styleEntry.bgColor;
                    }
                    if (styleEntry.textColor) {
                        rowStyle.color = styleEntry.textColor;
                    }
                }

                if (String(styleEntry.styleTarget || "none") === "cell" && String(filter.column || "") === String(columnKey || "")) {
                    if (styleEntry.bgColor) {
                        cellStyle.background = styleEntry.bgColor;
                    }
                    if (styleEntry.textColor) {
                        cellStyle.color = styleEntry.textColor;
                    }
                }
            });
        });

        return {
            row: buildInlineStyle(rowStyle),
            cell: buildInlineStyle(cellStyle),
        };
    }

    function buildDimensionKey(row, dimensions) {
        return (dimensions || []).map(function (dimension) {
            return String(row[dimension] ?? "Non renseigne").trim() || "Non renseigne";
        }).join("||");
    }

    function buildDimensionValueList(row, dimensions) {
        return (dimensions || []).map(function (dimension) {
            const value = String(row[dimension] ?? "Non renseigne").trim() || "Non renseigne";
            return { dimension: dimension, value: value };
        });
    }

    function buildMeasureLabel(measure) {
        const aggregation = String(measure?.aggregation || "count");
        const column = String(measure?.column || "");
        const matchValue = String(measure?.matchValue || "").trim();

        if (aggregation === "percentage") {
            return column ? "Pourcentage " + humanizeKey(column) : "Pourcentage";
        }

        if (aggregation === "count" || column === "") {
            if (column === "") {
                return "Nombre de lignes";
            }

            return matchValue !== ""
                ? 'Nombre ' + humanizeKey(column) + ' = "' + matchValue + '"'
                : "Nombre " + humanizeKey(column);
        }

        return aggregationLabel(aggregation) + " " + humanizeKey(column);
    }

    function buildTableHtml(result, widget) {
        if (result.tableVariant === "distribution") {
            return buildDistributionTableMarkup(result, widget, {
                wrapClass: "bi-widget-table-wrap bi-widget-distribution-surface",
                tableClass: "bi-widget-table bi-widget-distribution-table",
            });
        }

        if (result.tableVariant === "datatable") {
            return buildDatatableHtml(result, widget);
        }

        const headers = result.columns.map(function (column) {
            return "<th>" + escapeHtml(column.label) + "</th>";
        }).join("");
        const rows = result.rows.map(function (row) {
            return "<tr>" + result.columns.map(function (column) {
                return "<td>" + escapeHtml(String(row[column.key] ?? "")) + "</td>";
            }).join("") + "</tr>";
        }).join("");
        return '<div class="bi-widget-table-wrap"><table class="bi-widget-table">' + (!widget.hideText ? '<thead><tr>' + headers + '</tr></thead>' : '') + "<tbody>" + rows + "</tbody></table></div>";
    }

    function getDatatableInlineStyles(widget) {
        const styles = normalizeDatatableStyleConfig(widget.tableStyles);
        return {
            headerBg: styles.headerBgColor || "",
            headerText: styles.headerTextColor || "",
            rowBg: styles.rowBgColor || "",
            rowAltBg: styles.rowAltBgColor || "",
            cellBg: styles.cellBgColor || "",
            cellText: styles.cellTextColor || widget.valueColor || "",
        };
    }

    function getTableColumnStyle(widget, columnKey) {
        const entries = Array.isArray(widget?.tableColumnStyles) ? widget.tableColumnStyles : [];
        const match = entries.find(function (entry) {
            return String(entry?.key || "") === String(columnKey || "");
        });

        return match ? createTableColumnStyleEntry(match) : createTableColumnStyleEntry({ key: columnKey });
    }

    function buildDatatableHtml(result, widget, options) {
        const safeOptions = options && typeof options === "object" ? options : {};
        const preview = Boolean(safeOptions.preview);
        const columns = result.columns.slice(0, Math.max(1, Number(safeOptions.maxColumns || result.columns.length)));
        const rows = result.rows.slice(0, Math.max(1, Number(safeOptions.maxRows || result.rows.length)));
        const sourceRows = Array.isArray(result.sourceRows) ? result.sourceRows.slice(0, rows.length) : rows;
        const sortColumn = String(result.sortColumn || "");
        const sortDir = result.sortDir === "desc" ? "desc" : "asc";
        const styleConfig = getDatatableInlineStyles(widget);
        const wrapClass = preview ? "bi-preview-table-wrap bi-widget-datatable-wrap" : "bi-widget-table-wrap bi-widget-datatable-wrap";
        const tableClass = preview ? "bi-preview-table-real bi-widget-table bi-widget-datatable" : "bi-widget-table bi-widget-datatable";

        const headers = columns.map(function (column) {
            const isSorted = column.key === sortColumn;
            const indicator = isSorted ? (sortDir === "asc" ? " ▲" : " ▼") : "";
            const classes = "bi-datatable-th" + (isSorted ? " bi-datatable-th--sorted bi-datatable-th--" + sortDir : "");
            const style = [
                styleConfig.headerBg ? "background:" + styleConfig.headerBg : "",
                styleConfig.headerText ? "color:" + styleConfig.headerText : "",
            ].filter(Boolean).join(";");
            return '<th class="' + escapeHtml(classes) + '" data-sort-col="' + escapeHtml(column.key) + '"' + (style ? ' style="' + escapeHtml(style) + '"' : "") + '>' + escapeHtml(column.label) + escapeHtml(indicator) + "</th>";
        }).join("");

        const body = rows.map(function (row, rowIndex) {
            const sourceRow = sourceRows[rowIndex] && typeof sourceRows[rowIndex] === "object" ? sourceRows[rowIndex] : row;
            const conditionalRowStyles = getDatatableConditionalStyles(widget, sourceRow, "");
            const rowStyle = [
                rowIndex % 2 === 0
                    ? (styleConfig.rowBg ? "background:" + styleConfig.rowBg : "")
                    : (styleConfig.rowAltBg ? "background:" + styleConfig.rowAltBg : (styleConfig.rowBg ? "background:" + styleConfig.rowBg : "")),
                conditionalRowStyles.row,
            ].filter(Boolean).join(";");
            return "<tr" + (rowStyle ? ' style="' + escapeHtml(rowStyle) + '"' : "") + ">" + columns.map(function (column) {
                const conditionalCellStyles = getDatatableConditionalStyles(widget, sourceRow, column.key);
                const columnStyle = getTableColumnStyle(widget, column.key);
                const cellStyle = [
                    columnStyle.bgColor ? "--bi-datatable-col-bg:" + columnStyle.bgColor : "",
                    columnStyle.textColor ? "--bi-datatable-col-text:" + columnStyle.textColor : "",
                    columnStyle.bgColor ? "background-color:var(--bi-datatable-col-bg)" : (styleConfig.cellBg ? "background:" + styleConfig.cellBg : ""),
                    columnStyle.textColor ? "color:var(--bi-datatable-col-text)" : (styleConfig.cellText ? "color:" + styleConfig.cellText : ""),
                    conditionalCellStyles.cell,
                ].filter(Boolean).join(";");
                return "<td" + (cellStyle ? ' style="' + escapeHtml(cellStyle) + '"' : "") + ">" + escapeHtml(String(row[column.key] ?? "")) + "</td>";
            }).join("") + "</tr>";
        }).join("");

        return '<div class="' + escapeHtml(wrapClass) + '"><table class="' + escapeHtml(tableClass) + '">' +
            (!widget.hideText ? '<thead><tr>' + headers + '</tr></thead>' : '') +
            "<tbody>" + body + "</tbody></table></div>";
    }

    function buildDistributionTableMarkup(result, widget, options) {
        const safeOptions = options && typeof options === "object" ? options : {};
        const wrapClass = String(safeOptions.wrapClass || "bi-widget-table-wrap");
        const tableClass = String(safeOptions.tableClass || "bi-widget-table bi-widget-distribution-table");
        const maxRows = Number(safeOptions.maxRows || 0);
        const visibleRows = maxRows > 0 ? result.rows.slice(0, maxRows) : result.rows;
        const headers = [
            escapeHtml(String(result.dimensionLabel || "Categorie")),
            "Nombre",
            "%",
            "Repartition",
        ];
        const rows = visibleRows.map(function (row) {
            const count = formatCompactNumber(row.count);
            const percentage = formatDistributionPercentage(row.percentage);
            const width = Math.max(0, Math.min(100, Number(row.width || 0)));
            const color = String(row.color || "#3b82f6");
            const inlineLabel = width > 12 ? count : "";

            return '<div class="bi-distribution-row">' +
                '<div class="bi-distribution-row-main">' +
                '<div class="bi-distribution-cell bi-distribution-label">' + escapeHtml(String(row.label || "Non renseigne")) + '</div>' +
                '<div class="bi-distribution-cell bi-widget-distribution-number" style="color:' + escapeHtml(color) + ';">' + escapeHtml(count) + '</div>' +
                '<div class="bi-distribution-cell bi-distribution-percent">' + escapeHtml(percentage) + '</div>' +
                '</div>' +
                '<div class="bi-widget-distribution-gauge-cell">' +
                '<div class="bi-distribution-progress">' +
                '<div class="bi-distribution-progress-bar" style="--bi-distribution-bar-color:' + escapeHtml(color) + ';width:' + escapeHtml(String(width)) + '%;background:' + escapeHtml(color) + ';">' + escapeHtml(inlineLabel) + '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join("");
        const totalLabel = result.truncated ? "TOTAL ANALYSE" : "TOTAL";
        const totalRow = '<div class="bi-widget-distribution-total">' +
            '<div class="bi-distribution-row-main">' +
            '<div class="bi-distribution-cell bi-distribution-label"><strong>' + escapeHtml(totalLabel) + '</strong></div>' +
            '<div class="bi-distribution-cell bi-widget-distribution-number"><strong>' + escapeHtml(formatCompactNumber(result.totalCount)) + '</strong></div>' +
            '<div class="bi-distribution-cell bi-distribution-percent"><strong>100,0 %</strong></div>' +
            '</div>' +
            '</div>';

        return '<div class="' + escapeHtml(wrapClass) + '">' +
            '<div class="' + escapeAttribute(tableClass) + '">' +
            (!widget.hideText
                ? '<div class="bi-distribution-head">' +
                    '<div class="bi-distribution-row-main">' +
                    headers.slice(0, 3).map(function (label) {
                        return '<div class="bi-distribution-head-cell">' + label + '</div>';
                    }).join("") +
                    '</div>' +
                    '<div class="bi-distribution-head-gauge">' + headers[3] + '</div>' +
                '</div>'
                : '') +
            '<div class="bi-distribution-list">' + rows + totalRow + '</div>' +
            '</div>' +
            '</div>';
    }

    function buildEmptyStateHtml() {
        const page = getCurrentPage();
        if (page && page.isPlaceholder) {
            return "Aucune statistique BI n est disponible pour votre utilisateur ou votre profil.";
        }
        const suggestions = (Array.isArray(cfg.suggestedWidgets) ? cfg.suggestedWidgets : []).map(function (widget) {
            return '<button type="button" class="bi-palette-button" data-suggested-widget="' + escapeHtml(widget.type) + '">' + escapeHtml(widget.title || widget.type) + "</button>";
        }).join("");
        let html = "Ajoutez un premier composant depuis la palette pour construire votre page BI.";
        if (canEditCurrentPage() && state.editMode && suggestions) {
            html += '<div class="bi-widget-palette" style="margin-top:1rem;">' + suggestions + "</div>";
        }
        return html;
    }

    function bindEmptyStateActions() {
        dom.emptyState.querySelectorAll("[data-suggested-widget]").forEach(function (button) {
            button.addEventListener("click", function () {
                addWidget(String(button.getAttribute("data-suggested-widget") || ""));
            });
        });
    }

    function loadFiles(connectionId, selectedFileId, usePreloaded, forceRefresh) {
        if (!connectionId) {
            state.files = [];
            state.dataset = null;
            state.builderOptions = buildBuilderOptionsFromDataset(null);
            clearDataFeedback();
            renderAll();
            return;
        }

        if (usePreloaded && String(cfg.preloadedConnectionId || "") === connectionId && Array.isArray(cfg.preloadedFiles)) {
            state.files = cfg.preloadedFiles;
            const fileId = resolveSelectedFileId(state.files, selectedFileId);
            const page = getCurrentPage();
            if (page) {
                page.connectionId = connectionId;
                page.fileId = fileId;
            }
            renderFiles();
            if (fileId) {
                loadDataset(connectionId, fileId, Boolean(cfg.preloadedDataset && !cfg.preloadedDataset._error && String(cfg.preloadedFileId || "") === fileId), forceRefresh);
            } else {
                state.dataset = null;
                state.builderOptions = buildBuilderOptionsFromDataset(null);
                clearDataFeedback();
                setLoading(false);
                renderAll();
            }
            return;
        }

        clearDataFeedback();
        setLoading(true, "Chargement des fichiers SharePoint...");
        fetchJson(cfg.filesUrl + "?connection=" + encodeURIComponent(connectionId) + (forceRefresh ? "&refresh=1" : ""))
            .then(function (payload) {
                state.files = Array.isArray(payload.files) ? payload.files : [];
                const fileId = resolveSelectedFileId(state.files, selectedFileId);
                const page = getCurrentPage();
                if (page) {
                    page.connectionId = connectionId;
                    page.fileId = fileId;
                }
                renderFiles();
                if (fileId) {
                    loadDataset(connectionId, fileId, false, forceRefresh);
                    return;
                }
                state.dataset = null;
                state.builderOptions = buildBuilderOptionsFromDataset(null);
                clearDataFeedback();
                setLoading(false);
                renderAll();
            })
            .catch(function (error) {
                state.files = [];
                state.dataset = null;
                state.builderOptions = buildBuilderOptionsFromDataset(null);
                showDataFeedback(error.message || "Impossible de charger la liste des fichiers de la source.");
                renderAll();
                handleError(error);
            });
    }

    function loadDataset(connectionId, fileId, usePreloaded, forceRefresh) {
        if (!connectionId || !fileId) {
            state.dataset = null;
            state.builderOptions = buildBuilderOptionsFromDataset(null);
            clearDataFeedback();
            setLoading(false);
            renderAll();
            return;
        }

        if (!forceRefresh && usePreloaded && cfg.preloadedDataset && !cfg.preloadedDataset._error && String(cfg.preloadedConnectionId || "") === connectionId && String(cfg.preloadedFileId || "") === fileId) {
            state.dataset = cfg.preloadedDataset;
            state.builderOptions = buildBuilderOptionsFromDataset(state.dataset);
            saveDatasetPayloadToCache(connectionId, fileId, state.dataset);
            clearDataFeedback();
            renderAll();
            return;
        }

        if (!forceRefresh) {
            const cachedPayload = getCachedDatasetPayload(connectionId, fileId);
            if (cachedPayload) {
                state.dataset = cachedPayload;
                state.builderOptions = buildBuilderOptionsFromDataset(state.dataset);
                clearDataFeedback();
                renderAll();
                return;
            }
        }

        state.dataset = null;
        state.builderOptions = buildBuilderOptionsFromDataset(null);
        clearDataFeedback();
        setLoading(true, "Chargement du fichier de donnees...");

        let datasetUrl = cfg.datasetUrl + "?connection=" + encodeURIComponent(connectionId) + "&file=" + encodeURIComponent(fileId);
        if (forceRefresh) {
            datasetUrl += "&refresh=1";
        }

        fetchJson(datasetUrl)
            .then(function (payload) {
                state.dataset = payload;
                state.builderOptions = buildBuilderOptionsFromDataset(payload);
                saveDatasetPayloadToCache(connectionId, fileId, payload);
                clearDataFeedback();
                renderAll();
            })
            .catch(function (error) {
                const cachedFallback = getCachedDatasetPayload(connectionId, fileId);
                if (cachedFallback) {
                    state.dataset = cachedFallback;
                    state.builderOptions = buildBuilderOptionsFromDataset(cachedFallback);
                    showDataFeedback("Le dernier jeu de donnees local est affiche car le rechargement a echoue.", "info");
                    renderAll();
                    handleError(error);
                    return;
                }

                state.dataset = null;
                state.builderOptions = buildBuilderOptionsFromDataset(null);
                showDataFeedback(error.message || "Impossible de charger les donnees de la source.");
                renderAll();
                handleError(error);
            });
    }

    function getCachedDatasetPayload(connectionId, fileId) {
        if (typeof window.localStorage === "undefined") {
            return null;
        }

        const cacheKey = getDatasetBrowserCacheKey(connectionId, fileId);
        const raw = window.localStorage.getItem(cacheKey);
        if (!raw) {
            return null;
        }

        try {
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object" || !parsed.payload || typeof parsed.payload !== "object") {
                return null;
            }

            if (Date.now() - Number(parsed.cachedAt || 0) > datasetBrowserCacheTtlMs) {
                window.localStorage.removeItem(cacheKey);
                return null;
            }

            return parsed.payload;
        } catch (error) {
            return null;
        }
    }

    function saveDatasetPayloadToCache(connectionId, fileId, payload) {
        if (typeof window.localStorage === "undefined" || !payload || typeof payload !== "object" || payload._error) {
            return;
        }

        try {
            const cacheKey = getDatasetBrowserCacheKey(connectionId, fileId);
            const envelope = {
                cachedAt: Date.now(),
                payload: payload,
            };
            const serialized = JSON.stringify(envelope);
            if (serialized.length > datasetBrowserCacheMaxBytes) {
                window.localStorage.removeItem(cacheKey);
                return;
            }

            window.localStorage.setItem(cacheKey, serialized);
        } catch (error) {
            /* no-op */
        }
    }

    function getDatasetBrowserCacheKey(connectionId, fileId) {
        return String(cfg.browserCacheKey || "bi_browser_cache_0_v1")
            + ":dataset:"
            + String(connectionId || "")
            + ":"
            + String(fileId || "");
    }

    function handleUploadSourceSubmit(event) {
        event.preventDefault();
        if (!state.canManageSettings || !dom.uploadSourceForm || !dom.uploadSourceFile?.files?.length) {
            return;
        }

        const formData = new FormData(dom.uploadSourceForm);
        showSettingsFeedback("Import de la source...", "");
        showSaveStatus("Import de la source...", "");

        fetch(cfg.uploadSourceUrl, {
            method: "POST",
            headers: {
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: formData,
        })
            .then(function (response) {
                return parseJsonResponse(response, "Impossible d importer la source.");
            })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                const newConnectionId = getLatestModuleSourceId(state.moduleSettings.uploadedSources);
                dom.uploadSourceForm?.reset();
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange({
                    preferredConnectionId: newConnectionId,
                    forceRefresh: true,
                });
            })
            .then(function () {
                showSettingsFeedback("Source importee avec succes.", "is-success");
                showSaveStatus("Source importee", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible d importer la source.", "is-error");
                handleError(error);
            });
    }

    function handleRemoteSourceSubmit(event) {
        event.preventDefault();
        if (!state.canManageSettings) return;
        const label = String(dom.remoteSourceLabel?.value || "").trim();
        const url = String(dom.remoteSourceUrl?.value || "").trim();
        if (url === "") {
            showSettingsFeedback("Renseignez une URL SharePoint.", "is-error");
            return;
        }

        const remoteSourceError = getRemoteSourceUrlError(url);
        if (remoteSourceError) {
            showSettingsFeedback(remoteSourceError, "is-error");
            return;
        }

        showSettingsFeedback("Ajout de la source distante...", "");
        showSaveStatus("Ajout de la source distante...", "");
        fetchJson(cfg.settingsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: JSON.stringify({
                action: "add_remote_source",
                label: label,
                url: url,
            }),
            })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                const newConnectionId = getLatestModuleSourceId(state.moduleSettings.remoteSources);
                dom.remoteSourceForm?.reset();
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange({
                    preferredConnectionId: newConnectionId,
                    forceRefresh: true,
                });
            })
            .then(function () {
                showSettingsFeedback("URL SharePoint ajoutee avec succes.", "is-success");
                showSaveStatus("URL SharePoint ajoutee", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible d ajouter l URL SharePoint.", "is-error");
                handleError(error);
            });
    }

    function handleApiSourceSubmit(event) {
        event.preventDefault();
        if (!state.canManageSettings) return;

        const label = String(dom.apiSourceLabel?.value || "").trim();
        const url = String(dom.apiSourceUrl?.value || "").trim();
        const token = String(dom.apiSourceToken?.value || "").trim();

        if (url === "") {
            showSettingsFeedback("Renseignez une URL API.", "is-error");
            return;
        }

        const apiSourceError = getApiSourceUrlError(url);
        if (apiSourceError) {
            showSettingsFeedback(apiSourceError, "is-error");
            return;
        }

        if (token === "") {
            showSettingsFeedback("Renseignez le token API.", "is-error");
            return;
        }

        showSettingsFeedback("Ajout du webservice...", "");
        showSaveStatus("Ajout du webservice...", "");
        fetchJson(cfg.settingsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: JSON.stringify({
                action: "add_api_source",
                label: label,
                url: url,
                token: token,
            }),
        })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                const newConnectionId = getLatestModuleSourceId(state.moduleSettings.apiSources);
                dom.apiSourceForm?.reset();
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange({
                    preferredConnectionId: newConnectionId,
                    forceRefresh: true,
                });
            })
            .then(function () {
                showSettingsFeedback("Webservice ajoute avec succes.", "is-success");
                showSaveStatus("Webservice ajoute", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible d ajouter le webservice.", "is-error");
                handleError(error);
            });
    }

    function handleEditSourceSubmit(event) {
        event.preventDefault();
        if (!state.canManageSettings) return;

        const sourceId = String(dom.editSourceId?.value || state.editingModuleSourceId || "").trim();
        const label = String(dom.editSourceLabel?.value || "").trim();
        const url = String(dom.editSourceUrl?.value || "").trim();
        const token = String(dom.editSourceToken?.value || "").trim();
        const entry = findModuleSourceById(sourceId);

        if (!entry) {
            showSettingsFeedback("Source BI introuvable.", "is-error");
            return;
        }

        if (entry.kind === "remote") {
            if (url === "") {
                showSettingsFeedback("Renseignez une URL SharePoint.", "is-error");
                return;
            }

            const remoteSourceError = getRemoteSourceUrlError(url);
            if (remoteSourceError) {
                showSettingsFeedback(remoteSourceError, "is-error");
                return;
            }
        }

        if (entry.kind === "api") {
            if (url === "") {
                showSettingsFeedback("Renseignez une URL API.", "is-error");
                return;
            }

            const apiSourceError = getApiSourceUrlError(url);
            if (apiSourceError) {
                showSettingsFeedback(apiSourceError, "is-error");
                return;
            }
        }

        showSettingsFeedback("Mise a jour de la source...", "");
        showSaveStatus("Mise a jour de la source...", "");
        fetchJson(cfg.settingsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: JSON.stringify({
                action: "update_source",
                sourceId: sourceId,
                label: label,
                url: entry.kind === "uploaded" ? undefined : url,
                token: entry.kind === "api" ? token : undefined,
            }),
        })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                resetEditSourceForm();
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange({
                    preferredConnectionId: sourceId,
                    forceRefresh: true,
                });
            })
            .then(function () {
                const successMessage = entry.kind === "uploaded"
                    ? "Libelle de la source mis a jour."
                    : "Source mise a jour avec succes.";
                showSettingsFeedback(successMessage, "is-success");
                showSaveStatus("Source mise a jour", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible de modifier la source.", "is-error");
                handleError(error);
            });
    }

    function deleteModuleSource(sourceId) {
        if (!state.canManageSettings || sourceId === "") return;
        if (!window.confirm("Supprimer cette source BI ?")) return;

        showSettingsFeedback("Suppression de la source...", "");
        showSaveStatus("Suppression de la source...", "");
        fetchJson(cfg.settingsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: JSON.stringify({
                action: "delete_source",
                sourceId: sourceId,
            }),
        })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                if (String(state.editingModuleSourceId || "") === sourceId) {
                    resetEditSourceForm();
                }
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange();
            })
            .then(function () {
                showSettingsFeedback("Source supprimee avec succes.", "is-success");
                showSaveStatus("Source supprimee", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible de supprimer la source.", "is-error");
                handleError(error);
            });
    }

    function handleCreationPermissionsSubmit(event) {
        event.preventDefault();
        if (!state.canManageSettings) return;

        showSettingsFeedback("Enregistrement des droits de creation...", "");
        fetchJson(cfg.settingsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: JSON.stringify({
                action: "update_page_creation_permissions",
                userIds: getSelectedMultiValues(dom.creationPermissionsUsers).map(function (value) { return parseInt(value, 10) || 0; }).filter(Boolean),
                profileTypes: getSelectedMultiValues(dom.creationPermissionsProfiles),
            }),
        })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                renderSettingsModal();
                showSettingsFeedback("Droits de creation enregistres.", "is-success");
                showSaveStatus("Droits enregistres", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible d enregistrer les droits de creation.", "is-error");
                handleError(error);
            });
    }

    function handlePagePermissionsSubmit(event) {
        event.preventDefault();
        const page = getCurrentPage();
        if (!page || !canManageCurrentPagePermissions()) return;

        showSettingsFeedback("Enregistrement de la visibilite...", "");
        fetchJson(cfg.settingsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: JSON.stringify({
                action: "update_page_visibility",
                pageId: String(page.id || ""),
                userIds: getSelectedMultiValues(dom.pagePermissionsUsers).map(function (value) { return parseInt(value, 10) || 0; }).filter(Boolean),
                profileTypes: getSelectedMultiValues(dom.pagePermissionsProfiles),
            }),
        })
            .then(function (payload) {
                state.preferences = normalizePreferences(payload.preferences || state.preferences);
                state.canCreatePages = Boolean(state.preferences.canCreatePages);
                state.canManageSettings = Boolean(state.preferences.canManageSettings);
                state.selectedPageId = String(state.preferences.selectedPageId || state.selectedPageId || "");
                renderSettingsModal();
                renderAll();
                showSettingsFeedback("Visibilite de la page enregistree.", "is-success");
                showSaveStatus("Visibilite enregistree", "is-success");
            })
            .catch(function (error) {
                showSettingsFeedback(error.message || "Impossible d enregistrer la visibilite de la page.", "is-error");
                handleError(error);
            });
    }

    function getRemoteSourceUrlError(url) {
        let parsedUrl = null;
        try {
            parsedUrl = new URL(String(url || "").trim());
        } catch (error) {
            return "URL SharePoint invalide.";
        }

        const decodedPath = decodeURIComponent(String(parsedUrl.pathname || ""));
        const normalizedPath = decodedPath.toLowerCase();
        if (normalizedPath.indexOf("/:f:/") !== -1) {
            return "Ce lien SharePoint pointe vers un dossier. Utilisez un lien direct vers un fichier CSV, Excel ou JSON.";
        }

        const sharedFileMatch = normalizedPath.match(/\/:([a-z]):\//i);
        if (sharedFileMatch) {
            const sharedFileType = String(sharedFileMatch[1] || "").toLowerCase();
            if (sharedFileType === "x") {
                return "";
            }

            return "Seuls les fichiers CSV, Excel et JSON sont supportes pour les sources SharePoint.";
        }

        const pathSegments = decodedPath.split("/").filter(Boolean);
        const fileName = pathSegments.length ? pathSegments[pathSegments.length - 1] : "";
        const dotIndex = fileName.lastIndexOf(".");
        const extension = dotIndex >= 0 ? fileName.slice(dotIndex + 1).toLowerCase() : "";

        if (!fileName || !extension) {
            return "Le lien SharePoint doit pointer directement vers un fichier CSV, Excel ou JSON.";
        }

        if (["csv", "json", "xls", "xlsx"].indexOf(extension) === -1) {
            return "Seuls les fichiers CSV, Excel et JSON sont supportes pour les sources SharePoint.";
        }

        return "";
    }

    function getApiSourceUrlError(url) {
        try {
            new URL(String(url || "").trim());
        } catch (error) {
            return "URL API invalide.";
        }

        return "";
    }

    function refreshConnectionsAfterSettingsChange(options) {
        const preferredConnectionId = String(options?.preferredConnectionId || "");
        const forceRefresh = Boolean(options?.forceRefresh);

        return fetchJson(cfg.settingsUrl)
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                return fetchJson(cfg.connectionsUrl);
            })
            .then(function (payload) {
                state.connections = Array.isArray(payload.connections) ? payload.connections : [];
                const page = getCurrentPage();
                if (!page) {
                    renderAll();
                    return;
                }

                if (preferredConnectionId && state.connections.some(function (connection) { return connection.id === preferredConnectionId; })) {
                    page.connectionId = preferredConnectionId;
                    page.fileId = "";
                    state.dataset = null;
                    state.builderOptions = buildBuilderOptionsFromDataset(null);
                    renderAll();
                    loadFiles(preferredConnectionId, "", false, forceRefresh);
                    scheduleSavePreferences();
                    return;
                }

                let shouldSavePreferences = false;
                if (page.connectionId && !state.connections.some(function (connection) { return connection.id === page.connectionId; })) {
                    page.connectionId = "";
                    page.fileId = "";
                    state.files = [];
                    state.dataset = null;
                    state.builderOptions = buildBuilderOptionsFromDataset(null);
                    shouldSavePreferences = true;
                }
                renderAll();
                if (page.connectionId) {
                    loadFiles(page.connectionId, page.fileId || "", false, forceRefresh);
                    return;
                }
                if (shouldSavePreferences) {
                    scheduleSavePreferences();
                }
            });
    }

    function scheduleSavePreferences() {
        clearTimeout(state.saveTimer);
        state.preferencesRevision += 1;
        state.saveTimer = window.setTimeout(savePreferences, 350);
    }

    function flushPendingSavePreferences() {
        clearTimeout(state.saveTimer);
        state.saveTimer = 0;
        state.preferencesRevision += 1;
        savePreferences();
    }

    function resolveSelectedFileId(files, selectedFileId) {
        const normalizedFileId = String(selectedFileId || "");
        if (normalizedFileId && files.some(function (file) { return String(file.id || "") === normalizedFileId; })) {
            return normalizedFileId;
        }

        return String(files[0]?.id || "");
    }

    function getLatestModuleSourceId(sources) {
        if (!Array.isArray(sources) || !sources.length) {
            return "";
        }

        return String(sources[sources.length - 1]?.id || "");
    }

    function renderDataFeedback() {
        if (!dom.dataFeedback) return;
        const message = String(state.dataFeedbackMessage || "");
        dom.dataFeedback.hidden = message === "";
        dom.dataFeedback.textContent = message;
        dom.dataFeedback.classList.toggle("is-error", message !== "" && String(state.dataFeedbackType || "error") === "error");
    }

    function showDataFeedback(message, type) {
        state.dataFeedbackMessage = String(message || "");
        state.dataFeedbackType = state.dataFeedbackMessage === "" ? "" : String(type || "error");
        renderDataFeedback();
    }

    function clearDataFeedback() {
        state.dataFeedbackMessage = "";
        state.dataFeedbackType = "";
        renderDataFeedback();
    }

    function getInspectorDataHint(columns) {
        if (state.dataFeedbackMessage) {
            return String(state.dataFeedbackMessage);
        }

        if (!Array.isArray(columns) || columns.length === 0) {
            return "Aucune colonne disponible pour la source selectionnee.";
        }

        return "";
    }

    function getWidgetConfigurationHint(widget, columns) {
        const dataHint = getInspectorDataHint(columns);
        if (dataHint !== "") {
            return dataHint;
        }

        if (!widget) {
            return "";
        }

        const result = computeWidgetResult(widget);
        if (result.kind !== "empty") {
            return "";
        }

        return String(result.message || "");
    }

    function savePreferences() {
        if (!hasAnyEditablePage()) {
            return;
        }

        if (state.saveInFlight) {
            state.saveQueued = true;
            return;
        }

        const requestRevision = Number(state.preferencesRevision || 0);
        const requestPreferences = deepClone(state.preferences);
        state.saveInFlight = true;
        showSaveStatus("Enregistrement...", "");

        fetch(cfg.preferencesUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.preferencesCsrfToken || ""),
            },
            body: JSON.stringify({ preferences: requestPreferences }),
        })
            .then(function (response) {
                return parseJsonResponse(response, "Impossible d enregistrer la configuration BI.");
            })
            .then(function (payload) {
                if (requestRevision === Number(state.preferencesRevision || 0)) {
                    const nextPreferences = mergeSavedPreferences(requestPreferences, normalizePreferences(payload.preferences || requestPreferences));
                    if (!isWidgetModalOpen()) {
                        state.preferences = nextPreferences;
                        state.canCreatePages = Boolean(nextPreferences.canCreatePages);
                        state.canManageSettings = Boolean(nextPreferences.canManageSettings);
                        state.selectedPageId = String(nextPreferences.selectedPageId || state.selectedPageId || "");
                    }
                }
                showSaveStatus("Configuration enregistree", "is-success");
            })
            .catch(function (error) {
                showSaveStatus(error.message || "Erreur lors de l enregistrement.", "is-error");
            })
            .finally(function () {
                state.saveInFlight = false;
                if (state.saveQueued) {
                    state.saveQueued = false;
                    savePreferences();
                }
            });
    }

    function mergeSavedPreferences(requestPreferences, responsePreferences) {
        const requested = normalizePreferences(requestPreferences);
        const returned = normalizePreferences(responsePreferences);
        const requestPagesById = new Map((Array.isArray(requested.pages) ? requested.pages : []).map(function (page) {
            return [String(page.id || ""), page];
        }));

        returned.pages = (Array.isArray(returned.pages) ? returned.pages : []).map(function (page) {
            const requestPage = requestPagesById.get(String(page.id || ""));
            if (!requestPage) {
                return page;
            }

            if ((!Array.isArray(page.filters) || page.filters.length === 0) && Array.isArray(requestPage.filters) && requestPage.filters.length) {
                page.filters = requestPage.filters.map(function (filter) {
                    return {
                        column: String(filter.column || ""),
                        value: String(filter.value || ""),
                    };
                });
            }

            return page;
        });

        return returned;
    }

    function syncToolbarWithPage() {
        const page = getCurrentPage();
        if (!page) return;
        if (dom.connectionSelect) dom.connectionSelect.value = page.connectionId || "";
        if (page.connectionId && (!state.files.length || !state.files.some(function (file) { return file.id === page.fileId; }))) {
            loadFiles(page.connectionId, page.fileId || "", false);
            return;
        }
        if (dom.fileSelect) dom.fileSelect.value = page.fileId || "";
    }

    function getCurrentPage() {
        return state.preferences.pages.find(function (page) {
            return page.id === state.selectedPageId;
        }) || state.preferences.pages[0] || null;
    }

    function canEditCurrentPage() {
        const page = getCurrentPage();
        return Boolean(page && page.canEdit && !page.isPlaceholder);
    }

    function canManageCurrentPagePermissions() {
        const page = getCurrentPage();
        return Boolean(page && page.canManagePermissions && !page.isPlaceholder);
    }

    function hasAnyEditablePage() {
        return state.preferences.pages.some(function (page) {
            return Boolean(page && page.canEdit && !page.isPlaceholder);
        });
    }

    function getSelectedWidget() {
        const page = getCurrentPage();
        if (!page) return null;
        return page.widgets.find(function (widget) {
            return widget.id === state.selectedWidgetId;
        }) || null;
    }

    function getFilteredRows() {
        const page = getCurrentPage();
        const rows = Array.isArray(state.dataset?.rows) ? state.dataset.rows : [];
        if (!page || !page.filters.length) {
            return rows;
        }

        return rows.filter(function (row) {
            return page.filters.every(function (filter) {
                const column = String(filter?.column || "").trim();
                const value = String(filter?.value || "").trim();
                if (!column || !value) return true;
                return normalizeFilterComparableValue(row?.[column]) === normalizeFilterComparableValue(value);
            });
        });
    }

    function appendFilterDescription(message, filterDescription) {
        if (!filterDescription) {
            return message;
        }

        return message ? message + " • Filtre: " + filterDescription : "Filtre: " + filterDescription;
    }

    function appendFilterDescription(message, filterDescription) {
        if (!filterDescription) {
            return message;
        }

        return message ? message + " | Filtre: " + filterDescription : "Filtre: " + filterDescription;
    }

    function buildWidgetAggregateMeta(widget, aggregate, rowCount, filterDescription) {
        let meta = aggregate.meta;
        const isRowCount = String(widget.aggregation || "count") === "count" && !String(widget.valueColumn || "").trim();

        if (filterDescription) {
            if (isRowCount) {
                meta = rowCount + " lignes pour " + filterDescription;
            } else {
                meta = aggregate.meta + " pour " + filterDescription;
            }
        }

        return meta;
    }

    function formatDistributionPercentage(value) {
        return new Intl.NumberFormat("fr-FR", {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        }).format(Number(value || 0)) + " %";
    }

    function getDistinctColumnValues(columnKey, rows) {
        const values = [];
        if (!columnKey) return values;
        const sourceRows = Array.isArray(rows) ? rows : (Array.isArray(state.dataset?.rows) ? state.dataset.rows : []);
        sourceRows.forEach(function (row) {
            const value = String(row[columnKey] ?? "").trim();
            if (value !== "" && values.indexOf(value) === -1) values.push(value);
        });
        return values.sort(localeSort);
    }

    function buildDistinctValueOptions(columnKey, selectedValue, rows) {
        const values = getDistinctColumnValues(columnKey, rows);
        const options = [{ value: "", label: "Toutes les valeurs" }];

        values.forEach(function (value) {
            options.push({ value: value, label: value });
        });

        if (selectedValue && values.indexOf(String(selectedValue)) === -1) {
            options.push({ value: String(selectedValue), label: String(selectedValue) });
        }

        return options;
    }

    function getColumnOptions() {
        return Array.isArray(state.builderOptions?.columns) ? state.builderOptions.columns : [];
    }

    function getColumnLabel(columnKey) {
        const match = getColumnOptions().find(function (column) {
            return String(column.key || "") === String(columnKey || "");
        });

        return String(match?.label || humanizeKey(columnKey));
    }

    function getWidgetDefinition(type) {
        return state.widgetCatalog.find(function (item) { return item.type === type; }) || defaultWidgetCatalog[0];
    }

    function buildBuilderOptionsFromDataset(dataset) {
        const columns = Array.isArray(dataset?.columns) ? dataset.columns : [];
        return {
            widgets: mergeWidgetCatalog(cfg.builderOptions?.widgets),
            aggregations: [
                { key: "count", label: "Nombre de lignes" },
                { key: "sum", label: "Total" },
                { key: "avg", label: "Moyenne" },
                { key: "percentage", label: "Pourcentage" },
            ],
            layouts: [
                { key: "1/8", label: "1/8" },
                { key: "2/8", label: "2/8" },
                { key: "3/8", label: "3/8" },
                { key: "4/8", label: "4/8" },
                { key: "5/8", label: "5/8" },
                { key: "6/8", label: "6/8" },
                { key: "7/8", label: "7/8" },
                { key: "8/8", label: "8/8" },
            ],
            columns: columns.map(function (column) {
                return { key: String(column.key || ""), label: String(column.label || column.key || ""), type: String(column.type || "string") };
            }),
        };
    }

    function mergeWidgetCatalog(rawCatalog) {
        const incoming = Array.isArray(rawCatalog) ? rawCatalog : [];
        const byType = new Map();

        defaultWidgetCatalog.forEach(function (widget) {
            byType.set(String(widget.type), widget);
        });

        incoming.forEach(function (widget) {
            if (!widget || typeof widget !== "object") return;
            const type = String(widget.type || "").trim();
            if (!type) return;
            byType.set(type, Object.assign({}, byType.get(type) || {}, widget));
        });

        return Array.from(byType.values());
    }

    function normalizePreferences(preferences) {
        const safe = preferences && typeof preferences === "object" ? deepClone(preferences) : {};
        const pages = Array.isArray(safe.pages) ? safe.pages : [];
        if (!pages.length) {
            pages.push({
                id: "page-bi-1",
                name: "Page BI principale",
                connectionId: String(cfg.preloadedConnectionId || ""),
                fileId: String(cfg.preloadedFileId || ""),
                filters: [],
                widgets: [],
                ownerUserId: 0,
                ownerEmail: "",
                ownerDisplayName: "",
                allowedUserIds: [],
                allowedProfileTypes: [],
                canEdit: false,
                canManagePermissions: false,
                isPlaceholder: false,
            });
        }

        return {
            selectedPageId: String(safe.selectedPageId || pages[0].id || "page-bi-1"),
            canCreatePages: Boolean(safe.canCreatePages),
            canManageSettings: Boolean(safe.canManageSettings),
            pages: pages.map(function (page, pageIndex) {
                return {
                    id: String(page.id || "page-bi-" + (pageIndex + 1)),
                    name: String(page.name || "Page BI"),
                    connectionId: String(page.connectionId || cfg.preloadedConnectionId || ""),
                    fileId: String(page.fileId || cfg.preloadedFileId || ""),
                    filters: Array.isArray(page.filters) ? page.filters.map(function (filter) {
                        return { column: String(filter.column || ""), value: String(filter.value || "") };
                    }) : [],
                    widgets: Array.isArray(page.widgets) ? page.widgets.map(function (widget, widgetIndex) {
                        return normalizeWidget(widget, widgetIndex);
                    }) : [],
                    ownerUserId: parseInt(page.ownerUserId, 10) || 0,
                    ownerEmail: String(page.ownerEmail || ""),
                    ownerDisplayName: String(page.ownerDisplayName || ""),
                    allowedUserIds: Array.isArray(page.allowedUserIds) ? page.allowedUserIds.map(function (value) { return parseInt(value, 10) || 0; }).filter(Boolean) : [],
                    allowedProfileTypes: Array.isArray(page.allowedProfileTypes) ? page.allowedProfileTypes.map(function (value) { return String(value || ""); }).filter(Boolean) : [],
                    canEdit: Boolean(page.canEdit),
                    canManagePermissions: Boolean(page.canManagePermissions),
                    isPlaceholder: Boolean(page.isPlaceholder),
                };
            }),
        };
    }

    function normalizeModuleSettings(settings) {
        const safe = settings && typeof settings === "object" ? deepClone(settings) : {};
        return {
            uploadedSources: Array.isArray(safe.uploadedSources) ? safe.uploadedSources.map(function (source) {
                return {
                    id: String(source.id || ""),
                    label: String(source.label || ""),
                    fileName: String(source.fileName || ""),
                    path: String(source.path || ""),
                    extension: String(source.extension || ""),
                    uploadedAt: String(source.uploadedAt || ""),
                };
            }).filter(function (source) { return source.id !== ""; }) : [],
            remoteSources: Array.isArray(safe.remoteSources) ? safe.remoteSources.map(function (source) {
                return {
                    id: String(source.id || ""),
                    label: String(source.label || ""),
                    url: String(source.url || ""),
                    extension: String(source.extension || ""),
                    createdAt: String(source.createdAt || ""),
                };
            }).filter(function (source) { return source.id !== ""; }) : [],
            apiSources: Array.isArray(safe.apiSources) ? safe.apiSources.map(function (source) {
                return {
                    id: String(source.id || ""),
                    label: String(source.label || ""),
                    url: String(source.url || ""),
                    extension: String(source.extension || "json"),
                    createdAt: String(source.createdAt || ""),
                    tokenConfigured: Boolean(source.tokenConfigured),
                    tokenPreview: String(source.tokenPreview || ""),
                };
            }).filter(function (source) { return source.id !== ""; }) : [],
            pageCreationPermissions: {
                userIds: Array.isArray(safe.pageCreationPermissions?.userIds) ? safe.pageCreationPermissions.userIds.map(function (value) { return parseInt(value, 10) || 0; }).filter(Boolean) : [],
                profileTypes: Array.isArray(safe.pageCreationPermissions?.profileTypes) ? safe.pageCreationPermissions.profileTypes.map(function (value) { return String(value || ""); }).filter(Boolean) : [],
            },
        };
    }

    function normalizeRightsDirectory(directory) {
        const safe = directory && typeof directory === "object" ? deepClone(directory) : {};
        return {
            users: Array.isArray(safe.users) ? safe.users.map(function (user) {
                return {
                    id: parseInt(user.id, 10) || 0,
                    label: String(user.label || user.email || ""),
                    email: String(user.email || ""),
                };
            }).filter(function (user) { return user.id > 0; }) : [],
            profiles: Array.isArray(safe.profiles) ? safe.profiles.map(function (profile) {
                return String(profile || "");
            }).filter(Boolean) : [],
        };
    }

    function normalizeWidget(widget, widgetIndex) {
        const layout = fractions.indexOf(String(widget.layout || "")) !== -1
            ? String(widget.layout)
            : defaultLayoutForType(String(widget.type || "bar"));
        const rawDimensionColumn = String(widget.dimensionColumn || "").trim();
        const rawChartDimensions = Array.isArray(widget.chartDimensions) ? widget.chartDimensions.map(function (value) {
            return String(value || "");
        }).slice(0, 1) : [];
        const rawRowDimensions = Array.isArray(widget.rowDimensions) ? widget.rowDimensions.map(function (value) {
            return String(value || "");
        }).slice(0, 1) : [];
        const hasRawChartDimension = rawChartDimensions.some(function (value) {
            return String(value || "").trim() !== "";
        });
        const hasRawRowDimension = rawRowDimensions.some(function (value) {
            return String(value || "").trim() !== "";
        });

        const normalizedWidget = {
            id: String(widget.id || "widget-" + (widgetIndex + 1)),
            type: String(widget.type || "bar"),
            title: String(widget.title || ""),
            layout: layout,
            dimensionColumn: String(widget.dimensionColumn || ""),
            valueColumn: String(widget.valueColumn || ""),
            filterColumn: "",
            filterValue: "",
            percentageBase: String(String(widget.type || "bar") === "percentage" ? "group_share" : ""),
            targetGoal: "",
            aggregation: String(widget.aggregation || "count"),
            displayMode: String(widget.displayMode || "chart"),
            format: String(widget.format || ""),
            color: String(widget.color || ""),
            bgColor: String(widget.bgColor || ""),
            textColor: String(widget.textColor || ""),
            titleColor: String(widget.titleColor || ""),
            valueColor: String(widget.valueColor || ""),
            alignment: ["left", "center", "right"].indexOf(String(widget.alignment || "")) !== -1 ? String(widget.alignment) : "left",
            textSize: normalizeWidgetTextSize(widget.textSize),
            valueSize: normalizeWidgetValueSize(widget.valueSize),
            cardHeight: clamp(parseInt(widget.cardHeight, 10) || 75, 75, 520),
            hideTitle: Boolean(widget.hideTitle),
            hideText: Boolean(widget.hideText),
            hidden: Boolean(widget.hidden),
            maxItems: clamp(parseInt(widget.maxItems, 10) || 8, 3, 20),
            chartDimensions: hasRawChartDimension ? rawChartDimensions : (String(widget.type || "bar") === "table" ? [] : (rawDimensionColumn ? [rawDimensionColumn] : [])),
            rowDimensions: hasRawRowDimension ? rawRowDimensions : (String(widget.type || "bar") === "table" && rawDimensionColumn ? [rawDimensionColumn] : []),
            columnDimensions: [],
            measures: Array.isArray(widget.measures) ? widget.measures.map(function (measure) {
                return createMeasureEntry(measure);
            }).slice(0, 1) : [],
            widgetFilters: Array.isArray(widget.widgetFilters) ? widget.widgetFilters.map(function (filter) {
                return createFilterEntry(filter);
            }).slice(0, 5) : (
                String(widget.filterColumn || "").trim() !== "" && String(widget.filterValue || "").trim() !== ""
                    ? [createFilterEntry({
                        column: String(widget.filterColumn || ""),
                        value: String(widget.filterValue || ""),
                    })]
                    : []
            ),
            counterItems: [],
            tableColumns: Array.isArray(widget.tableColumns) ? widget.tableColumns.map(function (value) { return String(value || ""); }).filter(Boolean) : [],
            tableColumnStyles: Array.isArray(widget.tableColumnStyles) ? widget.tableColumnStyles.map(function (entry) {
                return createTableColumnStyleEntry(entry);
            }).filter(function (entry) {
                return entry.key !== "";
            }) : [],
            tableStyles: normalizeDatatableStyleConfig(widget.tableStyles),
            sortColumn: String(widget.sortColumn || ""),
            sortDir: String(widget.sortDir || "asc") === "desc" ? "desc" : "asc",
        };

        ensureWidgetDataModel(normalizedWidget);
        synchronizeLegacyWidgetFields(normalizedWidget);

        return normalizedWidget;
    }

    function buildSelectOptions(options, selectedValue, keyProperty) {
        const valueKey = keyProperty || "value";
        return options.map(function (option) {
            const value = String(option[valueKey] ?? option.key ?? "");
            const label = String(option.label ?? option[valueKey] ?? "");
            const selected = String(selectedValue || "") === value ? " selected" : "";
            return '<option value="' + escapeHtml(value) + '"' + selected + ">" + escapeHtml(label) + "</option>";
        }).join("");
    }

    function applyCardFraction(card, fraction) {
        const percent = fractionToPercent(fraction);
        const gap = dom.widgetsGrid ? (parseFloat(window.getComputedStyle(dom.widgetsGrid).gap) || 16) : 16;
        const parts = String(fraction || "4/8").split("/");
        const numerator = Number(parts[0] || 4);
        const denominator = Number(parts[1] || 8) || 8;
        card.style.flex = "0 0 calc(" + numerator + "/" + denominator + " * 100% - " + (gap * (7 / 8)) + "px)";
        card.style.flexBasis = "calc(" + numerator + "/" + denominator + " * 100% - " + (gap * (7 / 8)) + "px)";
        card.style.minWidth = "calc(" + percent + "% - " + gap + "px)";
        card.style.maxWidth = "100%";
    }

    function updateResizeButtonsState(card, widget) {
        const current = String(widget.layout || "4/8");
        const smallerBtn = card.querySelector('[data-direction="smaller"]');
        const largerBtn = card.querySelector('[data-direction="larger"]');
        if (smallerBtn) {
            smallerBtn.disabled = current === fractions[0];
            smallerBtn.hidden = false;
        }
        if (largerBtn) {
            largerBtn.disabled = current === fractions[fractions.length - 1];
            largerBtn.hidden = false;
        }
    }

    function updateVisibilityIcon(toggle, widget) {
        const isHidden = Boolean(widget.hidden);
        toggle.innerHTML = isHidden ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        toggle.classList.toggle("is-hidden", isHidden);
        toggle.setAttribute("aria-pressed", isHidden ? "true" : "false");
        toggle.title = isHidden ? "Afficher ce bloc" : "Masquer ce bloc";
    }

    function applyTextColorToCard(card, color) {
        card.style.color = color;
        card.querySelectorAll(".bi-widget-subtitle, .bi-widget-kpi-meta, .bi-widget-table, .bi-widget-table th, .bi-widget-table td, .bi-widget-empty").forEach(function (element) {
            element.style.color = color;
        });
    }

    function resetTextColorOnCard(card) {
        card.style.color = "";
        card.querySelectorAll(".bi-widget-subtitle, .bi-widget-kpi-meta, .bi-widget-table, .bi-widget-table th, .bi-widget-table td, .bi-widget-empty").forEach(function (element) {
            element.style.color = "";
        });
    }

    function normalizeColorInputValue(value) {
        const color = String(value || "").trim();
        if (/^#([0-9a-f]{6})$/i.test(color)) {
            return color.toLowerCase();
        }

        const rgb = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (rgb) {
            return "#" + [rgb[1], rgb[2], rgb[3]].map(function (part) {
                return Number(part).toString(16).padStart(2, "0");
            }).join("");
        }

        return "";
    }

    function formatIsoDate(value) {
        if (!value) return "";
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat("fr-FR", {
            dateStyle: "short",
            timeStyle: "short",
        }).format(parsed);
    }

    function fractionToPercent(fraction) {
        const parts = String(fraction || "4/8").split("/");
        const numerator = Number(parts[0] || 4);
        const denominator = Number(parts[1] || 8) || 8;
        return (numerator / denominator) * 100;
    }

    function getPrevFraction(current) {
        const index = Math.max(0, fractions.indexOf(current));
        return fractions[Math.max(0, index - 1)];
    }

    function getNextFraction(current) {
        const index = Math.max(0, fractions.indexOf(current));
        return fractions[Math.min(fractions.length - 1, index + 1)];
    }

    function formatValue(value, format, widget) {
        const numericValue = Number(value || 0);
        if (format === "currency") {
            return new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR", maximumFractionDigits: 0 }).format(numericValue);
        }
        if (format === "percent") {
            return new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(numericValue) + " %";
        }
        return new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 2 }).format(numericValue);
    }

    function formatCompactNumber(value) {
        return new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 1 }).format(Number(value || 0));
    }

    function humanizeKey(key) {
        return String(key || "").replace(/_/g, " ").replace(/\b\w/g, function (char) { return char.toUpperCase(); });
    }

    function defaultLayoutForType(type) {
        if (type === "kpi" || type === "counter" || type === "percentage") return "2/8";
        if (type === "table" || type === "datatable" || type === "distribution-table" || type === "line") return "8/8";
        return "4/8";
    }

    function aggregationLabel(aggregation) {
        if (aggregation === "sum") return "Total";
        if (aggregation === "avg") return "Moyenne";
        if (aggregation === "percentage") return "Pourcentage";
        return "Nombre";
    }

    function guessColorForWidget(widget) {
        const index = Math.abs(hashCode(String(widget.id || widget.type || ""))) % palette.length;
        return palette[index];
    }

    function toNumber(value) {
        const normalized = String(value ?? "").trim().replace(/\s/g, "").replace(",", ".");
        if (normalized === "" || !isFinite(Number(normalized))) return null;
        return Number(normalized);
    }

    function normalizeFilterComparableValue(value) {
        return String(value ?? "").trim().toLocaleLowerCase("fr-FR");
    }

    function showSaveStatus(message, cssClass) {
        if (!dom.saveStatus) return;
        dom.saveStatus.hidden = false;
        dom.saveStatus.textContent = message;
        dom.saveStatus.classList.remove("is-error", "is-success");
        if (cssClass) dom.saveStatus.classList.add(cssClass);
        clearTimeout(state.saveStatusTimer);
        state.saveStatusTimer = window.setTimeout(function () {
            dom.saveStatus.hidden = true;
        }, 2200);
    }

    function setLoading(isLoading, message) {
        if (dom.loading) {
            dom.loading.hidden = !isLoading;
            dom.loading.innerHTML = "<p>" + escapeHtml(message || "Chargement du module BI...") + "</p>";
        }
        if (dom.shell) dom.shell.hidden = isLoading;
    }

    function handleError(error) {
        setLoading(false);
        showSaveStatus(error.message || "Une erreur est survenue.", "is-error");
    }

    function fetchJson(url, options) {
        const requestOptions = Object.assign({}, options || {});
        requestOptions.headers = Object.assign({ "X-Requested-With": "XMLHttpRequest" }, requestOptions.headers || {});

        return fetch(url, requestOptions)
            .then(function (response) {
                return parseJsonResponse(response, "Erreur de chargement.");
            });
    }

    function parseJsonResponse(response, fallbackMessage) {
        return response.text().then(function (rawBody) {
            const body = String(rawBody || "");
            const trimmedBody = body.trim();
            let payload = {};

            if (trimmedBody !== "") {
                try {
                    payload = JSON.parse(trimmedBody);
                } catch (error) {
                    const contentType = String(response.headers.get("content-type") || "").toLowerCase();
                    if (contentType.indexOf("text/html") !== -1 || trimmedBody.startsWith("<")) {
                        throw new Error("Le serveur BI a renvoye une page HTML au lieu du JSON attendu.");
                    }

                    throw new Error(fallbackMessage || "Reponse JSON invalide du module BI.");
                }
            }

            if (!response.ok || payload._error) {
                throw new Error(payload._error || fallbackMessage || "Erreur de chargement.");
            }

            return payload;
        });
    }

    function destroyCharts() {
        Object.keys(state.charts).forEach(function (key) {
            if (state.charts[key] && typeof state.charts[key].destroy === "function") {
                state.charts[key].destroy();
            }
            delete state.charts[key];
        });
        destroyPreviewChart();
    }

    function destroyPreviewChart() {
        if (state.previewChart && typeof state.previewChart.destroy === "function") {
            state.previewChart.destroy();
        }
        state.previewChart = null;
    }

    function deepClone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function createId(prefix) {
        return prefix + "-" + Math.random().toString(36).slice(2, 9);
    }

    function parseOptionalInteger(value) {
        const normalized = String(value ?? "").trim();
        if (normalized === "") {
            return null;
        }

        const parsed = parseInt(normalized, 10);

        return Number.isFinite(parsed) ? parsed : null;
    }

    function normalizeWidgetTextSize(value, fallback) {
        const parsed = parseOptionalInteger(value);
        const resolvedFallback = Number.isFinite(fallback) ? fallback : WIDGET_TEXT_SIZE_DEFAULT;

        return clamp(
            parsed === null ? resolvedFallback : parsed,
            WIDGET_TEXT_SIZE_MIN,
            WIDGET_TEXT_SIZE_MAX
        );
    }

    function normalizeWidgetValueSize(value, fallback) {
        const parsed = parseOptionalInteger(value);
        const resolvedFallback = Number.isFinite(fallback) ? fallback : WIDGET_VALUE_SIZE_DEFAULT;

        return clamp(
            parsed === null ? resolvedFallback : parsed,
            WIDGET_VALUE_SIZE_MIN,
            WIDGET_VALUE_SIZE_MAX
        );
    }

    function syncWidgetTextSizeControls(value) {
        const normalizedValue = normalizeWidgetTextSize(value);

        if (dom.widgetTextSize) {
            dom.widgetTextSize.value = String(normalizedValue);
        }

        if (dom.widgetTextSizeNumber) {
            dom.widgetTextSizeNumber.value = String(normalizedValue);
        }

        if (dom.widgetTextSizeValue) {
            dom.widgetTextSizeValue.textContent = String(normalizedValue) + " px";
        }
    }

    function syncWidgetValueSizeControls(value) {
        const normalizedValue = normalizeWidgetValueSize(value);

        if (dom.widgetValueSize) {
            dom.widgetValueSize.value = String(normalizedValue);
        }

        if (dom.widgetValueSizeNumber) {
            dom.widgetValueSizeNumber.value = String(normalizedValue);
        }

        if (dom.widgetValueSizeValue) {
            dom.widgetValueSizeValue.textContent = String(normalizedValue) + " px";
        }
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function localeSort(left, right) {
        return String(left).localeCompare(String(right), "fr", { sensitivity: "base" });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function escapeAttribute(value) {
        return String(value).replace(/"/g, "&quot;");
    }

    function hashCode(value) {
        let hash = 0;
        for (let index = 0; index < value.length; index += 1) {
            hash = ((hash << 5) - hash) + value.charCodeAt(index);
            hash |= 0;
        }
        return hash;
    }

    function cleanupDraggingState() {
        if (state.draggingCard) {
            state.draggingCard.classList.remove("is-dragging", "dragging");
        }
        dom.widgetsGrid?.classList.remove("is-dragging", "dnd-active");
        state.draggingCard = null;
        state.insertBeforeEl = null;
        dndHideIndicator();
        if (state.dndIndicator && state.dndIndicator.parentNode) {
            state.dndIndicator.parentNode.removeChild(state.dndIndicator);
        }
        state.dndIndicator = null;
        if (state.dndRaf) {
            window.cancelAnimationFrame(state.dndRaf);
            state.dndRaf = 0;
        }
    }

    function dndComputeAndShow(grid, clientX, clientY) {
        const cards = Array.from(grid.querySelectorAll(".card:not(.dragging):not(.is-dragging)"));
        if (!cards.length) {
            state.insertBeforeEl = null;
            dndHideIndicator();
            return;
        }

        let bestSlot = null;
        cards.forEach(function (card) {
            const rect = card.getBoundingClientRect();
            const beforeSlot = {
                beforeEl: card,
                x: rect.left,
                y: rect.top,
                height: rect.height,
                distance: Math.hypot(clientX - rect.left, clientY - (rect.top + rect.height / 2)),
            };
            const afterSlot = {
                beforeEl: getNextWidgetCard(card),
                x: rect.right,
                y: rect.top,
                height: rect.height,
                distance: Math.hypot(clientX - rect.right, clientY - (rect.top + rect.height / 2)),
                anchorCard: card,
            };

            [beforeSlot, afterSlot].forEach(function (slot) {
                if (bestSlot === null || slot.distance < bestSlot.distance) {
                    bestSlot = slot;
                }
            });
        });

        if (bestSlot === null) {
            state.insertBeforeEl = null;
            dndHideIndicator();
            return;
        }

        state.insertBeforeEl = bestSlot.beforeEl;
        dndShowIndicator(grid, bestSlot);
    }

    function dndShowIndicator(grid, slot) {
        if (!state.dndIndicator) {
            state.dndIndicator = document.createElement("div");
            state.dndIndicator.className = "dnd-insertion-indicator";
            grid.appendChild(state.dndIndicator);
        }

        const gridRect = grid.getBoundingClientRect();
        state.dndIndicator.style.left = (slot.x - gridRect.left) + "px";
        state.dndIndicator.style.top = (slot.y - gridRect.top) + "px";
        state.dndIndicator.style.height = slot.height + "px";
        state.dndIndicator.classList.add("visible");
    }

    function dndHideIndicator() {
        if (state.dndIndicator) {
            state.dndIndicator.classList.remove("visible");
        }
    }

    function getNextWidgetCard(card) {
        let next = card.nextElementSibling;
        while (next) {
            if (next.hasAttribute("data-widget-id") && !next.classList.contains("is-dragging")) {
                return next;
            }
            next = next.nextElementSibling;
        }
        return null;
    }

    function createTransparentDragImage() {
        const image = new Image();
        image.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";
        return image;
    }
});
