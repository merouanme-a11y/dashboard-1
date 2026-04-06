document.addEventListener("DOMContentLoaded", function () {
    const cfg = window.__BI_CONFIG__ || {};
    const dom = {
        connectionSelect: document.getElementById("biConnectionSelect"),
        fileSelect: document.getElementById("biFileSelect"),
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
        widgetsGrid: document.getElementById("biWidgetsGrid"),
        emptyState: document.getElementById("biEmptyState"),
        filtersBar: document.getElementById("biFiltersBar"),
        widgetModal: document.getElementById("biWidgetModal"),
        widgetModalClose: document.getElementById("biWidgetModalClose"),
        inspectorHint: document.getElementById("biInspectorHint"),
        inspectorEmpty: document.getElementById("biInspectorEmpty"),
        inspectorForm: document.getElementById("biInspectorForm"),
        widgetPreview: document.getElementById("biWidgetPreview"),
        widgetTitle: document.getElementById("biWidgetTitle"),
        widgetType: document.getElementById("biWidgetType"),
        widgetLayout: document.getElementById("biWidgetLayout"),
        widgetAlignment: document.getElementById("biWidgetAlignment"),
        widgetCardHeight: document.getElementById("biWidgetCardHeight"),
        widgetCardHeightValue: document.getElementById("biWidgetCardHeightValue"),
        widgetDimension: document.getElementById("biWidgetDimension"),
        widgetValue: document.getElementById("biWidgetValue"),
        widgetAggregation: document.getElementById("biWidgetAggregation"),
        widgetFormat: document.getElementById("biWidgetFormat"),
        widgetMaxItems: document.getElementById("biWidgetMaxItems"),
        widgetMaxItemsValue: document.getElementById("biWidgetMaxItemsValue"),
        widgetTextSize: document.getElementById("biWidgetTextSize"),
        widgetTextSizeValue: document.getElementById("biWidgetTextSizeValue"),
        widgetValueSize: document.getElementById("biWidgetValueSize"),
        widgetValueSizeValue: document.getElementById("biWidgetValueSizeValue"),
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
        uploadSourceForm: document.getElementById("biUploadSourceForm"),
        uploadSourceLabel: document.getElementById("biUploadSourceLabel"),
        uploadSourceFile: document.getElementById("biUploadSourceFile"),
        remoteSourceForm: document.getElementById("biRemoteSourceForm"),
        remoteSourceLabel: document.getElementById("biRemoteSourceLabel"),
        remoteSourceUrl: document.getElementById("biRemoteSourceUrl"),
        settingsSourcesList: document.getElementById("biSettingsSourcesList"),
    };
    const palette = ["#2563eb", "#1d4ed8", "#0f766e", "#10b981", "#84cc16", "#eab308", "#f59e0b", "#f97316", "#ef4444", "#dc2626", "#ec4899", "#be185d", "#8b5cf6", "#7c3aed", "#06b6d4", "#334155"];
    const fractions = ["1/8", "2/8", "3/8", "4/8", "5/8", "6/8", "7/8", "8/8"];
    const transparentDragImage = createTransparentDragImage();
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
        { type: "table", label: "Tableau de donnees", icon: "bi-table", defaultTitle: "Tableau detaille" },
    ];
    const state = {
        connections: Array.isArray(cfg.preloadedConnections) ? cfg.preloadedConnections : [],
        files: Array.isArray(cfg.preloadedFiles) ? cfg.preloadedFiles : [],
        dataset: cfg.preloadedDataset && typeof cfg.preloadedDataset === "object" ? cfg.preloadedDataset : null,
        builderOptions: buildBuilderOptionsFromDataset(cfg.preloadedDataset),
        widgetCatalog: mergeWidgetCatalog(cfg.builderOptions?.widgets),
        preferences: normalizePreferences(cfg.preferences),
        moduleSettings: normalizeModuleSettings(cfg.moduleSettings),
        selectedPageId: "",
        selectedWidgetId: "",
        canEdit: Boolean(cfg.canEdit),
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
        saveStatusTimer: 0,
        activeColorPopover: null,
    };

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
    }

    function bindEvents() {
        dom.connectionSelect?.addEventListener("change", function () {
            const page = getCurrentPage();
            if (!page) return;
            page.connectionId = String(dom.connectionSelect.value || "");
            page.fileId = "";
            state.preferences.defaultConnection = page.connectionId;
            state.preferences.defaultFile = "";
            state.dataset = null;
            state.builderOptions = buildBuilderOptionsFromDataset(null);
            state.selectedWidgetId = "";
            loadFiles(page.connectionId, "", false);
            scheduleSavePreferences();
        });

        dom.fileSelect?.addEventListener("change", function () {
            const page = getCurrentPage();
            if (!page) return;
            page.fileId = String(dom.fileSelect.value || "");
            state.preferences.defaultFile = page.fileId;
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
            const name = window.prompt("Nom de la nouvelle page BI", "Nouvelle page BI");
            if (!name) return;
            const current = getCurrentPage();
            const nextPage = {
                id: createId("page"),
                name: String(name).trim() || "Nouvelle page BI",
                connectionId: current?.connectionId || state.preferences.defaultConnection || state.connections[0]?.id || "",
                fileId: current?.fileId || state.preferences.defaultFile || "",
                filters: [],
                widgets: [],
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
            const page = getCurrentPage();
            if (!page) return;
            const duplicate = deepClone(page);
            duplicate.id = createId("page");
            duplicate.name = page.name + " copie";
            duplicate.widgets = duplicate.widgets.map(function (widget) {
                widget.id = createId("widget");
                return widget;
            });
            state.preferences.pages.push(duplicate);
            state.selectedPageId = duplicate.id;
            state.preferences.selectedPageId = duplicate.id;
            state.selectedWidgetId = "";
            syncToolbarWithPage();
            renderAll();
            scheduleSavePreferences();
        });

        dom.deletePageBtn?.addEventListener("click", function () {
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
            if (!state.canEdit) return;
            renderSettingsModal();
            openModal("settings");
        });

        dom.editModeBtn?.addEventListener("click", function () {
            if (!state.canEdit) return;
            state.editMode = !state.editMode;
            if (!state.editMode) {
                state.selectedWidgetId = "";
                closeModal("widget");
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
            if (!page || !columns.length) return;
            const firstColumn = columns.find(function (column) { return column.type === "string" || column.type === "boolean"; }) || columns[0];
            const values = getDistinctColumnValues(firstColumn.key);
            page.filters.push({ column: firstColumn.key, value: values[0] || "" });
            renderAll();
            scheduleSavePreferences();
        });

        dom.widgetsGrid?.addEventListener("click", function (event) {
            if (!state.canEdit || !state.editMode) return;
            if (event.target === dom.widgetsGrid) {
                state.selectedWidgetId = "";
                closeModal("widget");
                renderInspector();
                renderWidgets();
            }
        });

        dom.widgetsGrid?.addEventListener("dragenter", function (event) {
            if (state.canEdit && state.editMode && state.draggingCard) {
                event.preventDefault();
            }
        });

        dom.widgetsGrid?.addEventListener("dragover", function (event) {
            if (!(state.canEdit && state.editMode) || !state.draggingCard) return;
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
            if (!(state.canEdit && state.editMode) || !state.draggingCard) return;
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
            if (!event.target.closest(".color-input-wrapper, .bi-modal-color-field")) {
                closeColorPopover();
            }
        });

        dom.uploadSourceForm?.addEventListener("submit", handleUploadSourceSubmit);
        dom.remoteSourceForm?.addEventListener("submit", handleRemoteSourceSubmit);

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

        dom.widgetDimension?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.dimensionColumn = String(dom.widgetDimension.value || "");
            refreshWidgetAfterModalEdit({ rebuildInspector: true });
        });

        dom.widgetValue?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.valueColumn = String(dom.widgetValue.value || "");
            refreshWidgetAfterModalEdit({ rebuildInspector: true });
        });

        dom.widgetAggregation?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.aggregation = String(dom.widgetAggregation.value || "count");
            refreshWidgetAfterModalEdit({ rebuildInspector: true });
        });

        dom.widgetFormat?.addEventListener("change", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.format = String(dom.widgetFormat.value || "");
            refreshWidgetAfterModalEdit();
        });

        dom.widgetMaxItems?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.maxItems = clamp(parseInt(dom.widgetMaxItems.value, 10) || 8, 3, 20);
            dom.widgetMaxItemsValue.textContent = String(widget.maxItems);
            refreshWidgetAfterModalEdit();
        });

        dom.widgetTextSize?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.textSize = clamp(parseInt(dom.widgetTextSize.value, 10) || 15, 12, 22);
            if (dom.widgetTextSizeValue) {
                dom.widgetTextSizeValue.textContent = String(widget.textSize) + " px";
            }
            refreshWidgetAfterModalEdit();
        });

        dom.widgetValueSize?.addEventListener("input", function () {
            const widget = getSelectedWidget();
            if (!widget) return;
            widget.valueSize = clamp(parseInt(dom.widgetValueSize.value, 10) || 48, 24, 72);
            if (dom.widgetValueSizeValue) {
                dom.widgetValueSizeValue.textContent = String(widget.valueSize) + " px";
            }
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
        dom.shell?.classList.toggle("is-edit-mode", state.canEdit && state.editMode);
        const showEditTools = state.canEdit && state.editMode;
        if (dom.addPageBtn) dom.addPageBtn.hidden = !showEditTools;
        if (dom.duplicatePageBtn) dom.duplicatePageBtn.hidden = !showEditTools;
        if (dom.deletePageBtn) dom.deletePageBtn.hidden = !showEditTools;
        if (dom.settingsBtn) dom.settingsBtn.hidden = !state.canEdit;
        renderConnections();
        renderFiles();
        renderPages();
        renderPalette();
        renderFiltersBar();
        renderWidgets();
        renderInspector();
        setLoading(false);
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
    }

    function renderPages() {
        if (!dom.pageSelect) return;
        dom.pageSelect.innerHTML = state.preferences.pages.map(function (page) {
            const selected = page.id === state.selectedPageId ? " selected" : "";
            return '<option value="' + escapeHtml(page.id) + '"' + selected + ">" + escapeHtml(page.name) + "</option>";
        }).join("");

        if (dom.deletePageBtn) {
            dom.deletePageBtn.disabled = state.preferences.pages.length <= 1;
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
            renderWidgets();
        }
        if (name === "settings" && dom.settingsModal) {
            dom.settingsModal.hidden = true;
        }
        closeColorPopover();
        syncModalBodyState();
    }

    function isWidgetModalOpen() {
        return Boolean(dom.widgetModal && dom.widgetModal.hidden === false);
    }

    function syncModalBodyState() {
        document.body.classList.toggle("bi-modal-open", Boolean((dom.widgetModal && !dom.widgetModal.hidden) || (dom.settingsModal && !dom.settingsModal.hidden)));
    }

    function refreshWidgetAfterModalEdit(options) {
        const widget = getSelectedWidget();
        if (!widget) return;

        if (isWidgetModalOpen()) {
            if (options?.rebuildInspector) {
                renderInspector();
            } else {
                updateInspectorPreview(widget);
            }
        } else if (options?.full) {
            renderAll();
        } else {
            renderWidgets();
        }

        scheduleSavePreferences();
    }

    function renderSettingsModal() {
        if (!dom.settingsSourcesList) return;
        const items = [];
        (state.moduleSettings.uploadedSources || []).forEach(function (source) {
            items.push({
                id: source.id,
                label: source.label || source.fileName || "Source importee",
                meta: [source.fileName || "", source.uploadedAt ? "Importe le " + formatIsoDate(source.uploadedAt) : "Source locale"].filter(Boolean).join(" • "),
                subtitle: source.path || "",
                kind: "Fichier du site",
            });
        });
        (state.moduleSettings.remoteSources || []).forEach(function (source) {
            items.push({
                id: source.id,
                label: source.label || "URL SharePoint",
                meta: [source.url || "", source.createdAt ? "Ajoutee le " + formatIsoDate(source.createdAt) : "Source distante"].filter(Boolean).join(" • "),
                subtitle: "URL SharePoint",
                kind: "URL distante",
            });
        });

        if (!items.length) {
            dom.settingsSourcesList.innerHTML = '<div class="bi-settings-empty">Aucune source personnalisee configuree pour le moment.</div>';
            return;
        }

        dom.settingsSourcesList.innerHTML = items.map(function (item) {
            return '<div class="bi-settings-source-item">' +
                '<div class="bi-settings-source-meta"><strong>' + escapeHtml(item.label) + '</strong><small>' + escapeHtml(item.kind) + '</small><small>' + escapeHtml(item.meta) + '</small></div>' +
                '<button type="button" class="stats-edit-button bi-icon-button bi-settings-source-remove" data-remove-source="' + escapeHtml(item.id) + '" title="Supprimer cette source" aria-label="Supprimer cette source"><i class="bi bi-trash3"></i></button>' +
                '</div>';
        }).join("");

        dom.settingsSourcesList.querySelectorAll("[data-remove-source]").forEach(function (button) {
            button.addEventListener("click", function () {
                deleteModuleSource(String(button.getAttribute("data-remove-source") || ""));
            });
        });
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
        popover.innerHTML = '<div class="bi-color-section"><div class="bi-color-section-title">Palette</div><div class="bi-color-swatch-grid">' + palette.map(function (color) {
            const activeClass = currentColor === color ? " is-active" : "";
            return '<button type="button" class="bi-color-swatch' + activeClass + '" data-color-value="' + escapeHtml(color) + '" style="--bi-swatch-color:' + escapeHtml(color) + ';" aria-label="Choisir ' + escapeHtml(color) + '"></button>';
        }).join("") + '</div></div><input type="color" class="bi-color-native-input" value="' + escapeHtml(currentColor || options.fallback || "#1f2937") + '" aria-label="Choisir une couleur"><input type="text" class="bi-color-hex-input" value="' + escapeHtml(currentColor || "") + '" placeholder="#FFFFFF" maxlength="7"><button type="button" class="bi-color-reset">Reinitialiser</button>';
        wrapper.appendChild(popover);

        const hexInput = popover.querySelector(".bi-color-hex-input");
        const nativeInput = popover.querySelector(".bi-color-native-input");
        const applyColor = function (value) {
            const normalized = normalizeColorInputValue(value || "");
            syncColorTrigger(trigger, normalized || "");
            options.setValue?.(normalized || "");
            popover.querySelectorAll(".bi-color-swatch").forEach(function (swatch) {
                swatch.classList.toggle("is-active", String(swatch.getAttribute("data-color-value") || "").toLowerCase() === normalized.toLowerCase());
            });
            if (hexInput && normalized) {
                hexInput.value = normalized.toUpperCase();
            }
            if (nativeInput && normalized) {
                nativeInput.value = normalized;
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

        state.activeColorPopover = { trigger: trigger, popover: popover };
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
        dom.builderTopbar.hidden = !(state.canEdit && state.editMode);
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
        const isEditMode = state.canEdit && state.editMode;
        dom.filtersBar.hidden = false;
        dom.filtersBar.innerHTML = page.filters.map(function (filter, index) {
            const values = getDistinctColumnValues(filter.column);
            const selectedColumn = columnOptions.find(function (column) {
                return column.key === filter.column;
            });
            const columnSelect = buildSelectOptions(columnOptions.map(function (column) {
                return { value: column.key, label: column.label };
            }), filter.column);
            const valueSelect = buildSelectOptions(values.map(function (value) {
                return { value: value, label: value };
            }), filter.value);
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
                filter.value = getDistinctColumnValues(filter.column)[0] || "";
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
                renderWidgets();
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

        updateInspectorPreview(getSelectedWidget());
    }

    function renderWidgetBody(card, widget, result) {
        const body = card.querySelector(".bi-widget-body");
        if (!body) return;
        const chartTextColor = widget.textColor || getComputedStyle(card).color || "#e5e7eb";
        const chartTextSize = clamp(parseInt(widget.textSize, 10) || 15, 12, 22);

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
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: result.horizontal ? "y" : "x",
                plugins: {
                    legend: {
                        display: !widget.hideText && (result.chartType !== "bar" || result.labels.length <= 12),
                        position: "bottom",
                        labels: {
                            color: chartTextColor,
                            font: { size: chartTextSize },
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
        dom.widgetTitle.value = widget.title || "";
        dom.widgetType.innerHTML = buildSelectOptions(state.widgetCatalog.map(function (item) {
            return { value: item.type, label: item.label };
        }), widget.type);
        dom.widgetLayout.innerHTML = buildSelectOptions(builder.layouts || [], widget.layout, "key");
        if (dom.widgetAlignment) dom.widgetAlignment.value = String(widget.alignment || "left");
        if (dom.widgetCardHeight) dom.widgetCardHeight.value = String(widget.cardHeight || 75);
        if (dom.widgetCardHeightValue) dom.widgetCardHeightValue.textContent = String(widget.cardHeight || 75) + " px";
        dom.widgetDimension.innerHTML = buildSelectOptions([{ key: "", label: "Aucune colonne" }].concat(builder.columns || []), widget.dimensionColumn, "key");
        dom.widgetValue.innerHTML = buildSelectOptions([{ key: "", label: "Aucune colonne" }].concat(builder.columns || []), widget.valueColumn, "key");
        dom.widgetAggregation.innerHTML = buildSelectOptions(builder.aggregations || [], widget.aggregation, "key");
        dom.widgetFormat.value = widget.format || "";
        dom.widgetMaxItems.value = String(widget.maxItems || 8);
        dom.widgetMaxItemsValue.textContent = String(widget.maxItems || 8);
        if (dom.widgetTextSize) dom.widgetTextSize.value = String(widget.textSize || 15);
        if (dom.widgetTextSizeValue) dom.widgetTextSizeValue.textContent = String(widget.textSize || 15) + " px";
        if (dom.widgetValueSize) dom.widgetValueSize.value = String(widget.valueSize || 48);
        if (dom.widgetValueSizeValue) dom.widgetValueSizeValue.textContent = String(widget.valueSize || 48) + " px";
        if (dom.widgetHideTitle) dom.widgetHideTitle.checked = Boolean(widget.hideTitle);
        if (dom.widgetHideText) dom.widgetHideText.checked = Boolean(widget.hideText);
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
        const textSize = clamp(parseInt(widget.textSize, 10) || 15, 12, 22);
        const valueSize = clamp(parseInt(widget.valueSize, 10) || 48, 24, 72);
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
        const chartTextSize = clamp(parseInt(widget.textSize, 10) || 15, 12, 22);

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
            if (event.target.closest && event.target.closest(".stats-resize-button, .card-visibility-toggle, .card-color-picker, .color-input-wrapper, .bi-color-popover, .bi-color-trigger, .bi-color-swatch, .bi-color-native-input, .bi-color-hex-input, .bi-color-reset, input, label, button")) {
                event.stopPropagation();
                return;
            }

            const actionButton = event.target.closest("[data-action]");
            if (actionButton) {
                handleWidgetAction(String(actionButton.getAttribute("data-action") || ""), index);
                event.stopPropagation();
                return;
            }

            if (!(state.canEdit && state.editMode)) {
                return;
            }
        });

        card.draggable = state.canEdit && state.editMode;
        card.classList.toggle("is-editable", state.canEdit && state.editMode);
        card.addEventListener("dragstart", function (event) {
            if (!(state.canEdit && state.editMode)) {
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
        if (!state.canEdit) {
            return;
        }

        ensureResizeControls(card, widget);
        ensureVisibilityToggle(card, widget);
    }

    function ensureResizeControls(card, widget) {
        const controls = document.createElement("div");
        controls.className = "stats-resize-controls";
        controls.hidden = !(state.canEdit && state.editMode);
        controls.innerHTML = '<button type="button" class="stats-resize-button" data-action="edit" title="Modifier le bloc"><i class="bi bi-pencil-square"></i></button><button type="button" class="stats-resize-button" data-direction="smaller" title="Reduire">-</button><button type="button" class="stats-resize-button" data-direction="larger" title="Agrandir">+</button><button type="button" class="stats-resize-button" data-action="duplicate" title="Dupliquer"><i class="bi bi-copy"></i></button><button type="button" class="stats-resize-button" data-action="delete" title="Supprimer"><i class="bi bi-trash3"></i></button>';
        card.appendChild(controls);

        controls.querySelectorAll(".stats-resize-button").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!(state.canEdit && state.editMode)) return;
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
            if (!state.canEdit) return;
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
        card.style.setProperty("--bi-widget-text-size", String(widget.textSize || 15) + "px");
        card.style.setProperty("--bi-widget-value-size", String(widget.valueSize || 48) + "px");
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
        if (!state.canEdit) return;
        const page = getCurrentPage();
        if (!page) return;
        const widgets = page.widgets;
        const widget = widgets[index];
        if (!widget) return;

        if (action === "edit") {
            state.selectedWidgetId = widget.id;
            renderWidgets();
            renderInspector();
            openModal("widget");
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
            duplicate.id = createId("widget");
            duplicate.title = String(widget.title || getWidgetDefinition(widget.type).defaultTitle || "Bloc BI");
            duplicate.layout = String(sourceCard?.getAttribute("data-card-fraction") || widget.layout || defaultLayoutForType(widget.type));
            duplicate.cardHeight = clamp(parseInt(widget.cardHeight, 10) || 75, 75, 520);
            duplicate.alignment = String(widget.alignment || "left");
            duplicate.textSize = clamp(parseInt(widget.textSize, 10) || 15, 12, 22);
            duplicate.valueSize = clamp(parseInt(widget.valueSize, 10) || 48, 24, 72);
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
        scheduleSavePreferences();
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
        if (!state.canEdit) return;
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
            aggregation: type === "table" ? "count" : "sum",
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
            cardHeight: type === "table" ? 320 : (type === "kpi" || type === "counter" || type === "percentage" ? 240 : 300),
            hideTitle: false,
            hideText: false,
            hidden: false,
            maxItems: type === "table" ? 10 : 8,
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

        if (!preserveTitle && (!widget.title || widget.title.trim() === "")) {
            widget.title = getWidgetDefinition(widget.type).defaultTitle || "Bloc BI";
        }

        if (widget.type === "line") {
            widget.dimensionColumn = widget.dimensionColumn || dateColumn?.key || dimension?.key || "";
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.aggregation = numeric ? "sum" : "count";
        } else if (widget.type === "bar" || widget.type === "bar-horizontal" || widget.type === "pie" || widget.type === "doughnut") {
            widget.dimensionColumn = widget.dimensionColumn || dimension?.key || dateColumn?.key || "";
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.aggregation = numeric ? "sum" : "count";
        } else if (widget.type === "histogram") {
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.aggregation = "count";
        } else if (widget.type === "table") {
            widget.dimensionColumn = widget.dimensionColumn || dimension?.key || "";
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.aggregation = "count";
        } else if (widget.type === "percentage") {
            widget.dimensionColumn = widget.dimensionColumn || dimension?.key || "";
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.aggregation = "percentage";
            widget.format = "percent";
        } else {
            widget.valueColumn = widget.valueColumn || numeric?.key || "";
            widget.dimensionColumn = widget.dimensionColumn || dimension?.key || "";
            widget.aggregation = numeric ? "sum" : "count";
        }

        if (!widget.color) {
            widget.color = guessColorForWidget(widget);
        }
    }

    function computeWidgetResult(widget) {
        const rows = getFilteredRows();
        if (!rows.length) {
            return { kind: "empty", message: "Aucune donnee disponible pour ce composant." };
        }

        const layout = widget.layout || "4/8";
        const title = widget.title || getWidgetDefinition(widget.type).defaultTitle || "Bloc BI";
        const valueColumn = widget.valueColumn || "";
        const dimensionColumn = widget.dimensionColumn || "";
        const aggregation = widget.aggregation || "count";
        const maxItems = clamp(parseInt(widget.maxItems, 10) || 8, 3, 20);
        const color = widget.color || guessColorForWidget(widget);

        if (widget.type === "kpi" || widget.type === "counter" || widget.type === "percentage") {
            const aggregate = aggregateRows(rows, aggregation, valueColumn);
            if (widget.type === "percentage") {
                const percent = computePercentage(rows, dimensionColumn, valueColumn);
                return {
                    kind: "kpi",
                    title: title,
                    value: formatValue(percent.value, "percent", widget),
                    meta: percent.meta,
                    color: color,
                    subtitle: layout === "2/8" ? "" : "Vue synthese",
                };
            }

            return {
                kind: "kpi",
                title: title,
                value: formatValue(aggregate.value, widget.format, widget),
                meta: aggregate.meta,
                color: color,
                subtitle: layout === "2/8" ? "" : "Vue synthetique",
            };
        }

        if (widget.type === "table") {
            const tableColumns = pickTableColumns(widget, rows);
            return {
                kind: "table",
                columns: tableColumns,
                rows: rows.slice(0, maxItems),
                subtitle: rows.length + " lignes source",
            };
        }

        if (widget.type === "histogram") {
            const histogram = buildHistogram(rows, valueColumn, maxItems, color);
            if (!histogram.labels.length) {
                return { kind: "empty", message: "Choisissez une colonne numerique pour l histogramme." };
            }
            return histogram;
        }

        const grouped = groupRows(rows, dimensionColumn, valueColumn, aggregation, maxItems);
        if (!grouped.labels.length) {
            return { kind: "empty", message: "Configurez les colonnes de regroupement et de valeur." };
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
            subtitle: grouped.subtitle,
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

    function groupRows(rows, dimensionColumn, valueColumn, aggregation, maxItems) {
        const groups = new Map();
        rows.forEach(function (row) {
            const label = String(dimensionColumn ? (row[dimensionColumn] || "Non renseigne") : "Ensemble").trim() || "Non renseigne";
            if (!groups.has(label)) {
                groups.set(label, []);
            }
            groups.get(label).push(row);
        });

        const items = Array.from(groups.entries()).map(function (entry) {
            return {
                label: entry[0],
                value: aggregateRows(entry[1], aggregation, valueColumn).raw,
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
            subtitle: aggregationLabel(aggregation) + (dimensionColumn ? " par " + humanizeKey(dimensionColumn) : ""),
        };
    }

    function aggregateRows(rows, aggregation, valueColumn) {
        if (aggregation === "count" || !valueColumn) {
            return { raw: rows.length, value: rows.length, meta: rows.length + " lignes source" };
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

    function computePercentage(rows, dimensionColumn, valueColumn) {
        if (dimensionColumn) {
            const topGroup = groupRows(rows, dimensionColumn, valueColumn, valueColumn ? "sum" : "count", 1);
            const allGroups = groupRows(rows, dimensionColumn, valueColumn, valueColumn ? "sum" : "count", 100);
            const total = allGroups.values.reduce(function (carry, value) { return carry + value; }, 0);
            const topValue = topGroup.values[0] || 0;
            const topLabel = topGroup.labels[0] || "Categorie";
            return {
                value: total > 0 ? (topValue / total) * 100 : 0,
                meta: topLabel + " represente " + formatCompactNumber(topValue),
            };
        }

        if (valueColumn) {
            const aggregate = aggregateRows(rows, "avg", valueColumn);
            return { value: aggregate.value, meta: aggregate.meta };
        }

        return { value: 100, meta: "Jeu de donnees charge" };
    }

    function pickTableColumns(widget, rows) {
        const keys = [];
        if (widget.dimensionColumn) keys.push(widget.dimensionColumn);
        if (widget.valueColumn && keys.indexOf(widget.valueColumn) === -1) keys.push(widget.valueColumn);
        Object.keys(rows[0] || {}).forEach(function (key) {
            if (keys.indexOf(key) === -1 && keys.length < 5) {
                keys.push(key);
            }
        });
        return keys.map(function (key) {
            return { key: key, label: humanizeKey(key) };
        });
    }

    function buildTableHtml(result, widget) {
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

    function buildEmptyStateHtml() {
        const suggestions = (Array.isArray(cfg.suggestedWidgets) ? cfg.suggestedWidgets : []).map(function (widget) {
            return '<button type="button" class="bi-palette-button" data-suggested-widget="' + escapeHtml(widget.type) + '">' + escapeHtml(widget.title || widget.type) + "</button>";
        }).join("");
        let html = "Ajoutez un premier composant depuis la palette pour construire votre page BI.";
        if (state.canEdit && state.editMode && suggestions) {
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
            renderAll();
            return;
        }

        if (usePreloaded && String(cfg.preloadedConnectionId || "") === connectionId && Array.isArray(cfg.preloadedFiles)) {
            state.files = cfg.preloadedFiles;
            const fileId = selectedFileId || state.files[0]?.id || "";
            const page = getCurrentPage();
            if (page) page.fileId = fileId;
            renderFiles();
            if (fileId) {
                loadDataset(connectionId, fileId, Boolean(cfg.preloadedDataset && !cfg.preloadedDataset._error && String(cfg.preloadedFileId || "") === fileId));
            } else {
                setLoading(false);
                renderAll();
            }
            return;
        }

        setLoading(true, "Chargement des fichiers SharePoint...");
        fetchJson(cfg.filesUrl + "?connection=" + encodeURIComponent(connectionId) + (forceRefresh ? "&refresh=1" : ""))
            .then(function (payload) {
                state.files = Array.isArray(payload.files) ? payload.files : [];
                const fileId = selectedFileId || state.files[0]?.id || "";
                const page = getCurrentPage();
                if (page) page.fileId = fileId;
                renderFiles();
                if (fileId) {
                    loadDataset(connectionId, fileId, false);
                    return;
                }
                state.dataset = null;
                setLoading(false);
                renderAll();
            })
            .catch(handleError);
    }

    function loadDataset(connectionId, fileId, usePreloaded) {
        if (!connectionId || !fileId) {
            state.dataset = null;
            state.builderOptions = buildBuilderOptionsFromDataset(null);
            setLoading(false);
            renderAll();
            return;
        }

        if (usePreloaded && cfg.preloadedDataset && !cfg.preloadedDataset._error && String(cfg.preloadedConnectionId || "") === connectionId && String(cfg.preloadedFileId || "") === fileId) {
            state.dataset = cfg.preloadedDataset;
            state.builderOptions = buildBuilderOptionsFromDataset(state.dataset);
            renderAll();
            return;
        }

        setLoading(true, "Chargement du fichier de donnees...");
        fetchJson(cfg.datasetUrl + "?connection=" + encodeURIComponent(connectionId) + "&file=" + encodeURIComponent(fileId))
            .then(function (payload) {
                state.dataset = payload;
                state.builderOptions = buildBuilderOptionsFromDataset(payload);
                renderAll();
            })
            .catch(handleError);
    }

    function handleUploadSourceSubmit(event) {
        event.preventDefault();
        if (!state.canEdit || !dom.uploadSourceForm || !dom.uploadSourceFile?.files?.length) {
            return;
        }

        const formData = new FormData(dom.uploadSourceForm);
        showSaveStatus("Import de la source...", "");

        fetch(cfg.uploadSourceUrl, {
            method: "POST",
            headers: {
                "X-CSRF-Token": String(cfg.settingsCsrfToken || ""),
            },
            body: formData,
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || payload._error) {
                        throw new Error(payload._error || "Impossible d importer la source.");
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                dom.uploadSourceForm?.reset();
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange();
            })
            .then(function () {
                showSaveStatus("Source importee", "is-success");
            })
            .catch(handleError);
    }

    function handleRemoteSourceSubmit(event) {
        event.preventDefault();
        if (!state.canEdit) return;
        const label = String(dom.remoteSourceLabel?.value || "").trim();
        const url = String(dom.remoteSourceUrl?.value || "").trim();
        if (url === "") return;

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
                dom.remoteSourceForm?.reset();
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange();
            })
            .then(function () {
                showSaveStatus("URL SharePoint ajoutee", "is-success");
            })
            .catch(handleError);
    }

    function deleteModuleSource(sourceId) {
        if (!state.canEdit || sourceId === "") return;
        if (!window.confirm("Supprimer cette source BI ?")) return;

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
                renderSettingsModal();
                return refreshConnectionsAfterSettingsChange();
            })
            .then(function () {
                showSaveStatus("Source supprimee", "is-success");
            })
            .catch(handleError);
    }

    function refreshConnectionsAfterSettingsChange() {
        return fetchJson(cfg.settingsUrl)
            .then(function (payload) {
                state.moduleSettings = normalizeModuleSettings(payload.settings || state.moduleSettings);
                return fetchJson(cfg.connectionsUrl);
            })
            .then(function (payload) {
                state.connections = Array.isArray(payload.connections) ? payload.connections : [];
                const page = getCurrentPage();
                if (!page) return;

                if (page.connectionId && !state.connections.some(function (connection) { return connection.id === page.connectionId; })) {
                    page.connectionId = "";
                    page.fileId = "";
                }
                renderAll();
            });
    }

    function scheduleSavePreferences() {
        clearTimeout(state.saveTimer);
        state.saveTimer = window.setTimeout(savePreferences, 350);
    }

    function savePreferences() {
        if (!state.canEdit) {
            return;
        }

        if (state.saveInFlight) {
            state.saveQueued = true;
            return;
        }

        state.saveInFlight = true;
        showSaveStatus("Enregistrement...", "");

        fetch(cfg.preferencesUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": String(cfg.preferencesCsrfToken || ""),
            },
            body: JSON.stringify({ preferences: state.preferences }),
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || payload._error) {
                        throw new Error(payload._error || "Impossible d enregistrer la configuration BI.");
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                state.preferences = normalizePreferences(payload.preferences || state.preferences);
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
                if (!filter.column || !filter.value) return true;
                return String(row[filter.column] ?? "") === String(filter.value);
            });
        });
    }

    function getDistinctColumnValues(columnKey) {
        const values = [];
        if (!columnKey) return values;
        (Array.isArray(state.dataset?.rows) ? state.dataset.rows : []).forEach(function (row) {
            const value = String(row[columnKey] ?? "").trim();
            if (value !== "" && values.indexOf(value) === -1) values.push(value);
        });
        return values.sort(localeSort);
    }

    function getColumnOptions() {
        return Array.isArray(state.builderOptions?.columns) ? state.builderOptions.columns : [];
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
                connectionId: String(safe.defaultConnection || cfg.preloadedConnectionId || ""),
                fileId: String(safe.defaultFile || cfg.preloadedFileId || ""),
                filters: [],
                widgets: [],
            });
        }

        return {
            defaultConnection: String(safe.defaultConnection || cfg.preloadedConnectionId || ""),
            defaultFile: String(safe.defaultFile || cfg.preloadedFileId || ""),
            selectedPageId: String(safe.selectedPageId || pages[0].id || "page-bi-1"),
            pages: pages.map(function (page, pageIndex) {
                return {
                    id: String(page.id || "page-bi-" + (pageIndex + 1)),
                    name: String(page.name || "Page BI"),
                    connectionId: String(page.connectionId || safe.defaultConnection || cfg.preloadedConnectionId || ""),
                    fileId: String(page.fileId || safe.defaultFile || cfg.preloadedFileId || ""),
                    filters: Array.isArray(page.filters) ? page.filters.map(function (filter) {
                        return { column: String(filter.column || ""), value: String(filter.value || "") };
                    }) : [],
                    widgets: Array.isArray(page.widgets) ? page.widgets.map(function (widget, widgetIndex) {
                        return normalizeWidget(widget, widgetIndex);
                    }) : [],
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
        };
    }

    function normalizeWidget(widget, widgetIndex) {
        const layout = fractions.indexOf(String(widget.layout || "")) !== -1
            ? String(widget.layout)
            : defaultLayoutForType(String(widget.type || "bar"));

        return {
            id: String(widget.id || "widget-" + (widgetIndex + 1)),
            type: String(widget.type || "bar"),
            title: String(widget.title || ""),
            layout: layout,
            dimensionColumn: String(widget.dimensionColumn || ""),
            valueColumn: String(widget.valueColumn || ""),
            aggregation: String(widget.aggregation || "count"),
            displayMode: String(widget.displayMode || "chart"),
            format: String(widget.format || ""),
            color: String(widget.color || ""),
            bgColor: String(widget.bgColor || ""),
            textColor: String(widget.textColor || ""),
            titleColor: String(widget.titleColor || ""),
            valueColor: String(widget.valueColor || ""),
            alignment: ["left", "center", "right"].indexOf(String(widget.alignment || "")) !== -1 ? String(widget.alignment) : "left",
            textSize: clamp(parseInt(widget.textSize, 10) || 15, 12, 22),
            valueSize: clamp(parseInt(widget.valueSize, 10) || 48, 24, 72),
            cardHeight: clamp(parseInt(widget.cardHeight, 10) || 75, 75, 520),
            hideTitle: Boolean(widget.hideTitle),
            hideText: Boolean(widget.hideText),
            hidden: Boolean(widget.hidden),
            maxItems: clamp(parseInt(widget.maxItems, 10) || 8, 3, 20),
        };
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
        if (smallerBtn) smallerBtn.disabled = current === fractions[0];
        if (largerBtn) largerBtn.disabled = current === fractions[fractions.length - 1];
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
        if (format === "currency" || (!format && widget?.aggregation === "sum")) {
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
        if (type === "table" || type === "line") return "8/8";
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
                return response.json().then(function (payload) {
                    if (!response.ok || payload._error) {
                        throw new Error(payload._error || "Erreur de chargement.");
                    }
                    return payload;
                });
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
