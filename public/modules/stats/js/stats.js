document.addEventListener("DOMContentLoaded", function () {
    const cfg = window.__STATS_CONFIG__ || {};
    const scope = String(cfg.cacheScope || "default").trim() || "default";
    const projectsUrl = String(cfg.projectsUrl || "");
    const ticketsUrl = String(cfg.ticketsUrl || "");
    const preferencesUrl = String(cfg.preferencesUrl || "");
    const preferencesCsrfToken = String(cfg.preferencesCsrfToken || "");
    const ticketsPageUrl = String(cfg.ticketsPageUrl || "");
    const preloadedProjects = cfg.preloadedProjects && typeof cfg.preloadedProjects === "object" ? cfg.preloadedProjects : null;
    const preloadedProjectId = String(cfg.preloadedProjectId || "").trim();
    const preloadedProjectTickets = cfg.preloadedProjectTickets && typeof cfg.preloadedProjectTickets === "object" ? cfg.preloadedProjectTickets : null;
    const initialPreferences = cfg.preferences && typeof cfg.preferences === "object" ? cfg.preferences : null;
    const keys = {
        projects: "stats_projects_cache_v2_" + scope,
        project: "stats_project_cache_v2_" + scope + "_",
        selection: "stats_tickets_selection_" + scope + "_",
        legacyDefaultProject: "stats_default_project_v2_" + scope,
        legacyLayout: "stats_layout_v2_" + scope + "_",
        legacyVisibility: "stats_visibility_v2_" + scope + "_",
        legacyColors: "stats_colors_v2_" + scope + "_",
    };
    const ttl = { projects: 1800000, project: 300000, selection: 900000, layout: 31536000000 };
    const palette = ["#3b82f6", "#f59e0b", "#8b5cf6", "#ec4899", "#10b981", "#ef4444", "#06b6d4", "#6c757d"];
    const fractions = ["1/8", "2/8", "3/8", "4/8", "5/8", "6/8", "7/8", "8/8"];
    const defaultFractions = { "card-total": "1/8", "card-states": "4/8", "card-services": "4/8", "card-users": "8/8", "card-table": "8/8" };
    const dom = {
        projectDropdownBtn: document.getElementById("projectDropdownBtn"),
        projectDropdownText: document.getElementById("projectDropdownText"),
        projectDropdownMenu: document.getElementById("projectDropdownMenu"),
        projectDropdownItems: document.getElementById("projectDropdownItems"),
        assigneeFilter: document.getElementById("assigneeFilter"),
        editModeBtn: document.getElementById("editModeBtn"),
        statsRefreshBtn: document.getElementById("statsRefreshBtn"),
        statsSaveStatus: document.getElementById("statsSaveStatus"),
        statsLoading: document.getElementById("statsLoading"),
        statsContent: document.getElementById("statsContent"),
        statsError: document.getElementById("statsError"),
        cardsGrid: document.getElementById("cardsGrid"),
        totalTickets: document.getElementById("totalTickets"),
        chartStates: document.getElementById("chartStates"),
        chartServices: document.getElementById("chartServices"),
        chartUsers: document.getElementById("chartUsers"),
        tableServices: document.getElementById("tableServices"),
    };
    const state = {
        projects: [],
        currentProject: "",
        selectedAssignee: "",
        tickets: [],
        filteredTickets: [],
        serviceColors: {},
        charts: {},
        isEditMode: false,
        draggingCard: null,
        dragSignature: "",
        layoutFrame: 0,
        resizeObserver: null,
        insertBeforeEl: null,
        dndIndicator: null,
        dndRaf: 0,
        preferences: normalizePreferences(initialPreferences),
        preferencesSaveTimer: 0,
        preferencesSaveInFlight: false,
        preferencesSaveQueued: false,
        preferencesStatusTimer: 0,
        legacyPreferencesMigrated: false,
    };

    bindStaticEvents();
    initLayoutObserver();
    loadProjects(false);

    function bindStaticEvents() {
        dom.projectDropdownBtn?.addEventListener("click", function (event) {
            event.stopPropagation();
            const open = !dom.projectDropdownMenu.hidden;
            dom.projectDropdownMenu.hidden = open;
            dom.projectDropdownBtn.classList.toggle("is-open", !open);
        });

        document.addEventListener("click", function (event) {
            if (!dom.projectDropdownBtn || !dom.projectDropdownMenu) return;
            if (dom.projectDropdownBtn.contains(event.target) || dom.projectDropdownMenu.contains(event.target)) return;
            dom.projectDropdownMenu.hidden = true;
            dom.projectDropdownBtn.classList.remove("is-open");
        });

        dom.assigneeFilter?.addEventListener("change", function () {
            state.selectedAssignee = String(dom.assigneeFilter.value || "");
            updateBrowserUrl();
            renderCurrentStatistics();
        });

        dom.editModeBtn?.addEventListener("click", function () {
            state.isEditMode = !state.isEditMode;
            dom.statsContent.classList.toggle("is-edit-mode", state.isEditMode);
            dom.editModeBtn.textContent = state.isEditMode ? "Fait" : "Modifier";
            enhanceCards();
            if (!state.isEditMode) {
                saveLayoutPreferences();
                saveVisibilityPreferences();
                saveColorPreferences();
            }
        });

        dom.statsRefreshBtn?.addEventListener("click", function () {
            loadProjects(true);
            if (state.currentProject) {
                loadProjectTickets(state.currentProject, true);
            }
        });

        dom.totalTickets?.closest(".stats-card")?.addEventListener("click", function () {
            if (state.isEditMode) return;
            redirectToTickets({}, state.filteredTickets, "Total");
        });

        if (dom.cardsGrid && window.getComputedStyle(dom.cardsGrid).position === "static") {
            dom.cardsGrid.style.position = "relative";
        }

        dom.cardsGrid?.addEventListener("dragenter", function (event) {
            if (state.isEditMode && state.draggingCard) {
                event.preventDefault();
            }
        });

        dom.cardsGrid?.addEventListener("dragover", function (event) {
            if (!state.isEditMode || !state.draggingCard) return;
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = "move";
            }
            if (state.dndRaf) return;
            state.dndRaf = window.requestAnimationFrame(function () {
                state.dndRaf = 0;
                dndComputeAndShow(dom.cardsGrid, event.clientX, event.clientY);
            });
        });

        dom.cardsGrid?.addEventListener("dragleave", function (event) {
            if (!state.draggingCard) return;
            const related = event.relatedTarget;
            if (related && dom.cardsGrid.contains(related)) return;
            dndHideIndicator();
        });

        dom.cardsGrid?.addEventListener("drop", function (event) {
            if (!state.isEditMode || !state.draggingCard) return;
            event.preventDefault();
            if (state.insertBeforeEl) {
                dom.cardsGrid.insertBefore(state.draggingCard, state.insertBeforeEl);
            } else {
                dom.cardsGrid.appendChild(state.draggingCard);
            }
            scheduleLayout();
            cleanupDraggingState();
            saveLayoutPreferences();
        });

        dom.cardsGrid?.addEventListener("dragend", function () {
            cleanupDraggingState();
        });
    }

    function initLayoutObserver() {
        if (!dom.cardsGrid || typeof ResizeObserver !== "function") return;
        if (state.resizeObserver) {
            state.resizeObserver.disconnect();
        }
        state.resizeObserver = new ResizeObserver(function () {
            scheduleLayout();
        });
        state.resizeObserver.observe(dom.cardsGrid);
    }

    function loadProjects(forceRefresh) {
        setError("");
        setLoadingState("Chargement des projets...");
        if (!forceRefresh) {
            const cached = getCachedPayload(keys.projects, ttl.projects);
            if (cached && Array.isArray(cached.projects)) {
                applyProjectsPayload(cached);
                return;
            }

            if (preloadedProjects && Array.isArray(preloadedProjects.projects)) {
                savePayloadToCache(keys.projects, preloadedProjects);
                applyProjectsPayload(preloadedProjects);
                return;
            }
        }
        fetchProjects(forceRefresh);
    }

    function fetchProjects(forceRefresh) {
        fetchJson(forceRefresh ? projectsUrl + "?refresh=1" : projectsUrl)
            .then(function (payload) {
                savePayloadToCache(keys.projects, payload);
                applyProjectsPayload(payload);
            })
            .catch(function (error) {
                setLoadingState(error.message || "Impossible de charger les projets.");
                setError(error.message || "Impossible de charger les projets.");
            });
    }

    function applyProjectsPayload(payload) {
        state.projects = Array.isArray(payload.projects) ? payload.projects : [];
        renderProjectsDropdown();
        if (!state.projects.length) {
            setLoadingState("Aucun projet disponible.");
            return;
        }
        maybeMigrateLegacyPreferences();
        const queryProject = getUrlParameter("project");
        const storedProject = state.preferences.defaultProject || readLegacyDefaultProject();
        const firstProject = String(state.projects[0].id || "");
        const nextProject = [queryProject, storedProject, firstProject].find(function (value) {
            return value && state.projects.some(function (project) { return String(project.id) === String(value); });
        }) || firstProject;
        selectProject(nextProject, false);
    }

    function renderProjectsDropdown() {
        if (!dom.projectDropdownItems) return;
        dom.projectDropdownItems.innerHTML = state.projects.map(function (project) {
            const projectId = escapeHtml(project.id || "");
            const projectName = escapeHtml(project.name || project.id || "");
            return '<div class="project-dropdown-item" data-project-id="' + projectId + '"><span class="project-dropdown-item-name">' + projectName + '</span><label class="project-dropdown-item-radio"><input type="radio" name="stats-project" value="' + projectId + '"><span>Par defaut</span></label></div>';
        }).join("");
        dom.projectDropdownItems.querySelectorAll(".project-dropdown-item").forEach(function (item) {
            item.addEventListener("click", function (event) {
                const projectId = String(item.getAttribute("data-project-id") || "");
                if (!projectId) return;
                const radio = item.querySelector('input[type="radio"]');
                if (radio && event.target !== radio) radio.checked = true;
                selectProject(projectId, true);
            });
        });
    }

    function selectProject(projectId, updateStorage) {
        if (!projectId) return;
        const changed = state.currentProject !== projectId;
        state.currentProject = projectId;
        if (updateStorage) {
            setDefaultProjectPreference(projectId);
            saveLegacyDefaultProject(projectId);
        }
        updateProjectSelectionUi(projectId);
        updateBrowserUrl();
        if (changed) loadProjectTickets(projectId, false);
    }

    function updateProjectSelectionUi(projectId) {
        const project = state.projects.find(function (candidate) { return String(candidate.id) === String(projectId); });
        if (dom.projectDropdownText) dom.projectDropdownText.textContent = project ? String(project.name || project.id) : "Selectionner un projet";
        dom.projectDropdownItems?.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.checked = String(radio.value) === String(projectId);
        });
        if (dom.projectDropdownMenu) dom.projectDropdownMenu.hidden = true;
        dom.projectDropdownBtn?.classList.remove("is-open");
    }

    function loadProjectTickets(projectId, forceRefresh) {
        if (!projectId) return;
        setError("");
        setLoadingState("Chargement des statistiques pour " + projectId + "...");
        const key = keys.project + projectId;
        if (!forceRefresh) {
            const cached = getCachedPayload(key, ttl.project);
            if (cached && Array.isArray(cached.tickets)) {
                applyProjectTicketsPayload(cached);
                return;
            }

            if (preloadedProjectTickets && preloadedProjectId === projectId && Array.isArray(preloadedProjectTickets.tickets)) {
                savePayloadToCache(key, preloadedProjectTickets);
                applyProjectTicketsPayload(preloadedProjectTickets);
                return;
            }
        }
        fetchProjectTickets(projectId, forceRefresh);
    }

    function fetchProjectTickets(projectId, forceRefresh) {
        fetchJson(ticketsUrl + "?project=" + encodeURIComponent(projectId) + (forceRefresh ? "&refresh=1" : ""))
            .then(function (payload) {
                savePayloadToCache(keys.project + projectId, payload);
                applyProjectTicketsPayload(payload);
            })
            .catch(function (error) {
                setLoadingState(error.message || "Impossible de charger les statistiques.");
                setError(error.message || "Impossible de charger les statistiques.");
            });
    }

    function applyProjectTicketsPayload(payload) {
        state.tickets = Array.isArray(payload.tickets) ? payload.tickets : [];
        state.serviceColors = payload.serviceColors && typeof payload.serviceColors === "object" ? payload.serviceColors : {};
        state.selectedAssignee = getUrlParameter("assignee") || "";
        rebuildAssigneeFilter();
        renderCurrentStatistics();
    }

    function rebuildAssigneeFilter() {
        if (!dom.assigneeFilter) return;
        const assignees = [];
        state.tickets.forEach(function (ticket) {
            const value = String(ticket.assignee || "").trim();
            if (value && assignees.indexOf(value) === -1) assignees.push(value);
        });
        assignees.sort(localeSort);
        const options = ['<option value="">Tous les responsables (global)</option>'];
        assignees.forEach(function (assignee) {
            options.push('<option value="' + escapeHtml(assignee) + '">' + escapeHtml(assignee) + "</option>");
        });
        dom.assigneeFilter.innerHTML = options.join("");
        if (state.selectedAssignee && assignees.indexOf(state.selectedAssignee) !== -1) {
            dom.assigneeFilter.value = state.selectedAssignee;
        } else {
            dom.assigneeFilter.value = "";
            state.selectedAssignee = "";
        }
    }

    function renderCurrentStatistics() {
        state.filteredTickets = filterTicketsByAssignee(state.selectedAssignee);
        const byState = aggregateBy(state.filteredTickets, "state");
        const byService = aggregateBy(state.filteredTickets, "service");
        const byUser = aggregateBy(state.filteredTickets, "assignee");
        renderStateCards(byState, state.filteredTickets);
        renderCharts(byState, byService, byUser, state.filteredTickets);
        renderServicesTable(byService, state.filteredTickets);
        if (dom.totalTickets) dom.totalTickets.textContent = String(state.filteredTickets.length);
        enhanceCards();
        applyStoredLayout();
        applyStoredVisibility();
        applyStoredColors();
        syncAllCardControls();
        scheduleLayout();
        if (dom.statsLoading) dom.statsLoading.hidden = true;
        if (dom.statsContent) dom.statsContent.hidden = false;
    }

    function renderStateCards(byState, tickets) {
        dom.cardsGrid?.querySelectorAll('[data-dynamic-state-card="1"]').forEach(function (card) { card.remove(); });
        const totalCard = dom.cardsGrid?.querySelector('[data-card-id="card-total"]');
        if (!dom.cardsGrid || !totalCard) return;
        const beforeNode = totalCard.nextSibling;
        Object.entries(byState).sort(function (left, right) {
            return Number(right[1] || 0) - Number(left[1] || 0);
        }).forEach(function (entry, index) {
            const stateName = entry[0];
            const count = Number(entry[1] || 0);
            const card = document.createElement("div");
            card.className = "card stats-card is-clickable";
            card.setAttribute("data-card-id", "card-state-" + slugify(stateName));
            card.setAttribute("data-card-fraction", "1/8");
            card.setAttribute("data-state-name", stateName);
            card.setAttribute("data-dynamic-state-card", "1");
            card.innerHTML = '<div class="stats-card-value state-count" style="color:' + escapeHtml(palette[index % palette.length]) + ';">' + escapeHtml(String(count)) + '</div><div class="stats-card-label">' + escapeHtml(stateName) + "</div>";
            card.addEventListener("click", function () {
                if (state.isEditMode) return;
                redirectToTickets({ state: stateName }, tickets.filter(function (ticket) {
                    return String(ticket.state || "").trim() === stateName;
                }), stateName);
            });
            dom.cardsGrid.insertBefore(card, beforeNode);
        });
    }

    function renderCharts(byState, byService, byUser, tickets) {
        destroyChart("chartStates");
        destroyChart("chartServices");
        destroyChart("chartUsers");

        if (dom.chartStates) {
            const labels = Object.keys(byState);
            state.charts.chartStates = new Chart(dom.chartStates, {
                type: "doughnut",
                data: { labels: labels, datasets: [{ data: labels.map(function (label) { return byState[label]; }), backgroundColor: labels.map(function (_, i) { return palette[i % palette.length]; }), borderColor: "rgba(255,255,255,0.85)", borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, onClick: function (_, elements) {
                    if (!elements.length || state.isEditMode) return;
                    const stateName = labels[elements[0].index] || "";
                    redirectToTickets({ state: stateName }, tickets.filter(function (ticket) { return String(ticket.state || "").trim() === stateName; }), stateName);
                } },
            });
        }

        if (dom.chartServices) {
            const labels = Object.keys(byService);
            state.charts.chartServices = new Chart(dom.chartServices, {
                type: "pie",
                data: { labels: labels, datasets: [{ data: labels.map(function (label) { return byService[label]; }), backgroundColor: labels.map(function (label, i) { return state.serviceColors[label] || palette[i % palette.length]; }), borderColor: "rgba(255,255,255,0.85)", borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, onClick: function (_, elements) {
                    if (!elements.length || state.isEditMode) return;
                    const service = labels[elements[0].index] || "";
                    redirectToTickets({ service: service }, tickets.filter(function (ticket) { return String(ticket.service || "").trim() === service; }), service);
                } },
            });
        }

        if (dom.chartUsers) {
            const labels = Object.keys(byUser);
            state.charts.chartUsers = new Chart(dom.chartUsers, {
                type: "bar",
                data: { labels: labels, datasets: [{ label: "Tickets", data: labels.map(function (label) { return byUser[label]; }), backgroundColor: "#3b82f6", borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } }, onClick: function (_, elements) {
                    if (!elements.length || state.isEditMode) return;
                    const assignee = labels[elements[0].index] || "";
                    redirectToTickets({ responsable: assignee }, tickets.filter(function (ticket) {
                        return String(ticket.assignee || ticket.reporter || "Non assigne").trim() === assignee;
                    }), assignee);
                } },
            });
        }
    }

    function renderServicesTable(byService, tickets) {
        if (!dom.tableServices) return;
        const entries = Object.entries(byService).sort(function (left, right) { return Number(right[1] || 0) - Number(left[1] || 0); });
        if (!entries.length) {
            dom.tableServices.innerHTML = '<div class="stats-empty">Aucune donnee disponible.</div>';
            return;
        }
        const total = entries.reduce(function (sum, entry) { return sum + Number(entry[1] || 0); }, 0);
        const maxCount = Math.max.apply(null, entries.map(function (entry) { return Number(entry[1] || 0); }));
        let html = '<table class="stats-table"><thead><tr><th>Service</th><th>Tickets</th><th>%</th><th>Repartition</th></tr></thead><tbody>';
        entries.forEach(function (entry, index) {
            const service = entry[0];
            const count = Number(entry[1] || 0);
            const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : "0.0";
            const width = maxCount > 0 ? (count / maxCount) * 100 : 0;
            const color = state.serviceColors[service] || palette[index % palette.length];
            html += '<tr class="is-clickable" data-service="' + escapeHtml(service) + '"><td>' + escapeHtml(service) + '</td><td style="font-weight:700;color:' + escapeHtml(color) + ';">' + escapeHtml(String(count)) + '</td><td>' + escapeHtml(percentage) + '%</td><td><div class="stats-progress"><div class="stats-progress-bar" style="width:' + escapeHtml(String(width)) + "%;background:" + escapeHtml(color) + ';">' + (width > 12 ? escapeHtml(String(count)) : "") + "</div></div></td></tr>";
        });
        html += '<tr><td><strong>TOTAL</strong></td><td><strong>' + escapeHtml(String(total)) + '</strong></td><td><strong>100%</strong></td><td></td></tr></tbody></table>';
        dom.tableServices.innerHTML = html;
        dom.tableServices.querySelectorAll("tr[data-service]").forEach(function (row) {
            row.addEventListener("click", function () {
                if (state.isEditMode) return;
                const service = String(row.getAttribute("data-service") || "");
                redirectToTickets({ service: service }, tickets.filter(function (ticket) {
                    return String(ticket.service || "").trim() === service;
                }), service);
            });
        });
    }

    function enhanceCards() {
        if (!dom.cardsGrid) return;
        dom.cardsGrid.querySelectorAll(".card").forEach(function (card) {
            const cardId = String(card.getAttribute("data-card-id") || "");
            if (!card.hasAttribute("data-card-fraction")) card.setAttribute("data-card-fraction", defaultFractions[cardId] || "1/8");
            applyCardFraction(card, String(card.getAttribute("data-card-fraction") || "1/8"));
            ensureResizeControls(card);
            ensureVisibilityToggle(card);
            ensureColorPicker(card);
            card.draggable = state.isEditMode;
            card.classList.toggle("is-editable", state.isEditMode);
            if (card.dataset.statsEnhanced === "1") {
                updateResizeButtonsState(card);
                return;
            }
            card.dataset.statsEnhanced = "1";
            card.addEventListener("dragstart", function (event) {
                if (!state.isEditMode) {
                    event.preventDefault();
                    return;
                }
                if (event.target.closest && event.target.closest(".stats-resize-button, .card-visibility-toggle, .card-color-picker, .color-input-wrapper, input, label, button")) {
                    event.preventDefault();
                    return;
                }
                state.draggingCard = card;
                state.dragSignature = "";
                state.insertBeforeEl = null;
                window.requestAnimationFrame(function () {
                    card.classList.add("is-dragging", "dragging");
                    dom.cardsGrid?.classList.add("is-dragging", "dnd-active");
                });
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = "move";
                    event.dataTransfer.dropEffect = "move";
                    event.dataTransfer.setData("text/plain", cardId || "stats-card");
                }
            });
            card.addEventListener("dragend", function () {
                cleanupDraggingState();
                saveLayoutPreferences();
            });
            card.addEventListener("dblclick", function () {
                if (!state.isEditMode) return;
                setCardFraction(card, getNextFraction(String(card.getAttribute("data-card-fraction") || "1/8")));
            });
            updateResizeButtonsState(card);
        });
    }

    function ensureResizeControls(card) {
        let controls = card.querySelector(".stats-resize-controls");
        if (!controls) {
            controls = document.createElement("div");
            controls.className = "stats-resize-controls";
            controls.innerHTML = '<button type="button" class="stats-resize-button" data-direction="smaller" title="Reduire">-</button><button type="button" class="stats-resize-button" data-direction="larger" title="Agrandir">+</button>';
            card.appendChild(controls);
            controls.querySelectorAll(".stats-resize-button").forEach(function (button) {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (!state.isEditMode) return;
                    const current = String(card.getAttribute("data-card-fraction") || "1/8");
                    const next = button.getAttribute("data-direction") === "smaller" ? getPrevFraction(current) : getNextFraction(current);
                    setCardFraction(card, next);
                });
            });
        }
        controls.hidden = !state.isEditMode;
    }

    function ensureVisibilityToggle(card) {
        if (card.querySelector(".card-visibility-toggle")) return;
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "card-visibility-toggle";
        toggle.title = "Afficher ou masquer ce bloc";
        toggle.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            card.classList.toggle("card-hidden");
            updateVisibilityIcon(toggle, card);
            reorganizeCards();
            saveVisibilityPreferences();
        });
        card.appendChild(toggle);
        updateVisibilityIcon(toggle, card);
    }

    function ensureColorPicker(card) {
        if (card.querySelector(".card-color-picker")) return;
        const picker = document.createElement("div");
        picker.className = "card-color-picker";
        picker.innerHTML = '<div class="color-input-wrapper"><label title="Couleur de fond">BG</label><input type="color" class="bg-color-input" title="Couleur de fond"></div><div class="color-input-wrapper"><label title="Couleur du texte">TX</label><input type="color" class="text-color-input" title="Couleur du texte"></div>';
        const bgInput = picker.querySelector(".bg-color-input");
        const textInput = picker.querySelector(".text-color-input");
        bgInput.value = normalizeColorInputValue(card.dataset.bgColor || getComputedStyle(card).backgroundColor || "#1f2937");
        textInput.value = normalizeColorInputValue(card.dataset.textColor || getComputedStyle(card).color || "#ffffff");
        bgInput.addEventListener("input", function (event) {
            card.dataset.bgColor = event.target.value;
            card.style.backgroundColor = event.target.value;
        });
        bgInput.addEventListener("change", function () {
            saveColorPreferences();
        });
        textInput.addEventListener("input", function (event) {
            card.dataset.textColor = event.target.value;
            applyTextColorToCard(card, event.target.value);
        });
        textInput.addEventListener("change", function () {
            saveColorPreferences();
        });
        card.appendChild(picker);
    }

    function setCardFraction(card, fraction) {
        card.setAttribute("data-card-fraction", fraction);
        applyCardFraction(card, fraction);
        updateResizeButtonsState(card);
        scheduleLayout();
        saveLayoutPreferences();
    }

    function applyTextColorToCard(card, color) {
        card.dataset.textColor = color;
        card.style.color = color;
        const controls = card.querySelectorAll(".card-color-picker, .stats-resize-controls, .card-visibility-toggle");
        const controlSet = new Set(controls);
        card.querySelectorAll("*").forEach(function (element) {
            let isControl = controlSet.has(element);
            if (!isControl) {
                controls.forEach(function (control) {
                    if (!isControl && control.contains(element)) {
                        isControl = true;
                    }
                });
            }
            if (!isControl) {
                element.style.color = color;
            }
        });
    }

    function applyCardFraction(card, fraction) {
        const percent = fractionToPercent(fraction);
        const gap = dom.cardsGrid ? (parseFloat(window.getComputedStyle(dom.cardsGrid).gap) || 16) : 16;
        const parts = String(fraction || "1/8").split("/");
        const numerator = Number(parts[0] || 1);
        const denominator = Number(parts[1] || 8) || 8;
        card.style.flex = "0 0 calc(" + numerator + "/" + denominator + " * 100% - " + (gap * (7 / 8)) + "px)";
        card.style.flexBasis = "calc(" + numerator + "/" + denominator + " * 100% - " + (gap * (7 / 8)) + "px)";
        card.style.minWidth = "calc(" + percent + "% - " + gap + "px)";
        card.style.maxWidth = "100%";
    }

    function updateResizeButtonsState(card) {
        const current = String(card.getAttribute("data-card-fraction") || "1/8");
        const smallerBtn = card.querySelector('[data-direction="smaller"]');
        const largerBtn = card.querySelector('[data-direction="larger"]');
        if (smallerBtn) smallerBtn.disabled = current === fractions[0];
        if (largerBtn) largerBtn.disabled = current === fractions[fractions.length - 1];
    }

    function saveLayoutPreferences() {
        if (!dom.cardsGrid || !state.currentProject) return;
        const cards = [];
        dom.cardsGrid.querySelectorAll(".card").forEach(function (card) {
            const id = String(card.getAttribute("data-card-id") || "");
            if (!id) return;
            cards.push({ id: id, fraction: String(card.getAttribute("data-card-fraction") || "1/8") });
        });
        saveProjectPreferenceCards(state.currentProject, "layout", cards);
        savePayloadToCache(keys.legacyLayout + state.currentProject, { cards: cards });
        scheduleLayout();
    }

    function saveVisibilityPreferences() {
        if (!dom.cardsGrid || !state.currentProject) return;
        const cards = [];
        dom.cardsGrid.querySelectorAll(".card").forEach(function (card) {
            const id = String(card.getAttribute("data-card-id") || "");
            if (!id) return;
            cards.push({ id: id, hidden: card.classList.contains("card-hidden") });
        });
        saveProjectPreferenceCards(state.currentProject, "visibility", cards);
        savePayloadToCache(keys.legacyVisibility + state.currentProject, { cards: cards });
    }

    function saveColorPreferences() {
        if (!dom.cardsGrid || !state.currentProject) return;
        const cards = [];
        dom.cardsGrid.querySelectorAll(".card").forEach(function (card) {
            const id = String(card.getAttribute("data-card-id") || "");
            const bgColor = String(card.dataset.bgColor || "");
            const textColor = String(card.dataset.textColor || "");
            if (!id || (!bgColor && !textColor)) return;
            cards.push({ id: id, bgColor: bgColor || null, textColor: textColor || null });
        });
        saveProjectPreferenceCards(state.currentProject, "colors", cards);
        savePayloadToCache(keys.legacyColors + state.currentProject, { cards: cards });
    }

    function applyStoredLayout() {
        if (!dom.cardsGrid || !state.currentProject) return;
        const layout = getProjectPreferenceCards(state.currentProject, "layout");
        if (!layout.length) return;
        const cardMap = {};
        dom.cardsGrid.querySelectorAll(".card").forEach(function (card) {
            const id = String(card.getAttribute("data-card-id") || "");
            if (id) cardMap[id] = card;
        });
        const fragment = document.createDocumentFragment();
        const appended = [];
        layout.forEach(function (item) {
            const id = String(item.id || "");
            const card = cardMap[id];
            if (!card) return;
            fragment.appendChild(card);
            appended.push(id);
            if (item.fraction) {
                card.setAttribute("data-card-fraction", String(item.fraction));
                applyCardFraction(card, String(item.fraction));
            }
        });
        dom.cardsGrid.querySelectorAll(".card").forEach(function (card) {
            const id = String(card.getAttribute("data-card-id") || "");
            if (appended.indexOf(id) === -1) fragment.appendChild(card);
        });
        dom.cardsGrid.innerHTML = "";
        dom.cardsGrid.appendChild(fragment);
        enhanceCards();
        scheduleLayout();
    }

    function applyStoredVisibility() {
        if (!dom.cardsGrid || !state.currentProject) return;
        const visibility = getProjectPreferenceCards(state.currentProject, "visibility");
        visibility.forEach(function (item) {
            const card = dom.cardsGrid.querySelector('[data-card-id="' + cssEscape(String(item.id || "")) + '"]');
            if (!card) return;
            card.classList.toggle("card-hidden", Boolean(item.hidden));
            const toggle = card.querySelector(".card-visibility-toggle");
            if (toggle) updateVisibilityIcon(toggle, card);
        });
        reorganizeCards();
    }

    function applyStoredColors() {
        if (!dom.cardsGrid || !state.currentProject) return;
        const colors = getProjectPreferenceCards(state.currentProject, "colors");
        colors.forEach(function (item) {
            const card = dom.cardsGrid.querySelector('[data-card-id="' + cssEscape(String(item.id || "")) + '"]');
            if (!card) return;
            if (item.bgColor) {
                card.dataset.bgColor = String(item.bgColor);
                card.style.backgroundColor = String(item.bgColor);
            }
            if (item.textColor) {
                applyTextColorToCard(card, String(item.textColor));
            }
        });
    }

    function cleanupDraggingState() {
        if (state.draggingCard) {
            state.draggingCard.classList.remove("is-dragging", "dragging");
        }
        dom.cardsGrid?.classList.remove("is-dragging", "dnd-active");
        state.draggingCard = null;
        state.dragSignature = "";
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

    function reorganizeCards() {
        if (!dom.cardsGrid) return;
        const allCards = Array.from(dom.cardsGrid.querySelectorAll(".card"));
        const visibleCards = allCards.filter(function (card) { return !card.classList.contains("card-hidden"); });
        const hiddenCards = allCards.filter(function (card) { return card.classList.contains("card-hidden"); });
        visibleCards.concat(hiddenCards).forEach(function (card) {
            dom.cardsGrid.appendChild(card);
        });
        scheduleLayout();
    }

    function updateVisibilityIcon(toggle, card) {
        const isHidden = card.classList.contains("card-hidden");
        toggle.innerHTML = isHidden ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        toggle.classList.toggle("hidden", isHidden);
    }

    function syncAllCardControls() {
        dom.cardsGrid?.querySelectorAll(".card").forEach(function (card) {
            const picker = card.querySelector(".card-color-picker");
            if (!picker) return;
            const bgInput = picker.querySelector(".bg-color-input");
            const textInput = picker.querySelector(".text-color-input");
            if (bgInput) {
                bgInput.value = normalizeColorInputValue(card.dataset.bgColor || getComputedStyle(card).backgroundColor || "#1f2937");
            }
            if (textInput) {
                textInput.value = normalizeColorInputValue(card.dataset.textColor || getComputedStyle(card).color || "#ffffff");
            }
        });
    }

    function redirectToTickets(extraFilters, tickets, label) {
        const key = keys.selection + Date.now() + "_" + Math.random().toString(16).slice(2);
        const filters = { project: state.currentProject, responsable: state.selectedAssignee || "", state: "", service: "", action: "" };
        Object.keys(extraFilters || {}).forEach(function (name) { filters[name] = String(extraFilters[name] || ""); });
        savePayloadToCache(key, { label: label || "", filters: filters, tickets: Array.isArray(tickets) ? tickets.slice() : [], serviceColors: state.serviceColors });
        const params = new URLSearchParams();
        params.set("statsSelection", key);
        if (filters.project) params.set("project", filters.project);
        if (filters.responsable) params.set("responsable", filters.responsable);
        if (filters.state) params.set("state", filters.state);
        if (filters.service) params.set("service", filters.service);
        if (filters.action) params.set("action", filters.action);
        window.location.href = ticketsPageUrl + "?" + params.toString();
    }

    function filterTicketsByAssignee(assignee) {
        const value = String(assignee || "").trim();
        if (!value) return state.tickets.slice();
        return state.tickets.filter(function (ticket) { return String(ticket.assignee || "").trim() === value; });
    }

    function aggregateBy(tickets, field) {
        const result = {};
        tickets.forEach(function (ticket) {
            const value = field === "assignee" ? String(ticket.assignee || ticket.reporter || "Non assigne").trim() : String(ticket[field] || "").trim();
            const label = value || (field === "service" ? "Non defini" : "Inconnu");
            result[label] = (result[label] || 0) + 1;
        });
        return result;
    }

    function destroyChart(name) {
        if (!state.charts[name]) return;
        state.charts[name].destroy();
        delete state.charts[name];
    }

    function setLoadingState(message) {
        if (dom.statsLoading) {
            dom.statsLoading.hidden = false;
            dom.statsLoading.innerHTML = "<p>" + escapeHtml(message) + "</p>";
        }
        if (dom.statsContent) dom.statsContent.hidden = true;
    }

    function setError(message) {
        if (!dom.statsError) return;
        dom.statsError.hidden = !message;
        dom.statsError.textContent = message || "";
    }

    function updateBrowserUrl() {
        const params = new URLSearchParams(window.location.search);
        if (state.currentProject) params.set("project", state.currentProject);
        if (state.selectedAssignee) params.set("assignee", state.selectedAssignee);
        else params.delete("assignee");
        window.history.replaceState({}, "", window.location.pathname + "?" + params.toString());
    }

    function normalizePreferences(payload) {
        const normalized = {
            defaultProject: normalizePreferenceKey(payload && payload.defaultProject),
            projects: {},
        };
        const rawProjects = payload && typeof payload === "object" && payload.projects && typeof payload.projects === "object"
            ? payload.projects
            : {};
        Object.keys(rawProjects).forEach(function (projectId) {
            const normalizedProjectId = normalizePreferenceKey(projectId);
            if (!normalizedProjectId) return;
            const projectPreferences = normalizeProjectPreferences(rawProjects[projectId]);
            if (!projectHasPreferenceData(projectPreferences)) return;
            normalized.projects[normalizedProjectId] = projectPreferences;
        });

        return normalized;
    }

    function normalizeProjectPreferences(payload) {
        const raw = payload && typeof payload === "object" ? payload : {};

        return {
            layout: normalizeLayoutCards(raw.layout),
            visibility: normalizeVisibilityCards(raw.visibility),
            colors: normalizeColorCards(raw.colors),
        };
    }

    function normalizeLayoutCards(value) {
        const cards = normalizeCardsSource(value);
        const normalized = [];
        const seen = {};
        cards.forEach(function (card) {
            if (!card || typeof card !== "object") return;
            const id = normalizePreferenceKey(card.id);
            if (!id || seen[id]) return;
            const fraction = fractions.indexOf(String(card.fraction || "")) !== -1 ? String(card.fraction) : "1/8";
            normalized.push({ id: id, fraction: fraction });
            seen[id] = true;
        });

        return normalized;
    }

    function normalizeVisibilityCards(value) {
        const cards = normalizeCardsSource(value);
        const normalized = [];
        const seen = {};
        cards.forEach(function (card) {
            if (!card || typeof card !== "object") return;
            const id = normalizePreferenceKey(card.id);
            if (!id || seen[id]) return;
            normalized.push({ id: id, hidden: toBoolean(card.hidden) });
            seen[id] = true;
        });

        return normalized;
    }

    function normalizeColorCards(value) {
        const cards = normalizeCardsSource(value);
        const normalized = [];
        const seen = {};
        cards.forEach(function (card) {
            if (!card || typeof card !== "object") return;
            const id = normalizePreferenceKey(card.id);
            if (!id || seen[id]) return;
            const bgColor = normalizeHexColor(card.bgColor);
            const textColor = normalizeHexColor(card.textColor);
            if (!bgColor && !textColor) return;
            normalized.push({ id: id, bgColor: bgColor || null, textColor: textColor || null });
            seen[id] = true;
        });

        return normalized;
    }

    function normalizeCardsSource(value) {
        if (Array.isArray(value)) return value;
        if (value && typeof value === "object" && Array.isArray(value.cards)) return value.cards;

        return [];
    }

    function normalizePreferenceKey(value) {
        return String(value || "").trim().slice(0, 120);
    }

    function normalizeHexColor(value) {
        const color = String(value || "").trim();
        if (!color || /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(color) !== true) return null;

        return normalizeColorInputValue(color).toLowerCase();
    }

    function toBoolean(value) {
        if (value === true || value === 1 || value === "1") return true;

        return String(value || "").trim().toLowerCase() === "true";
    }

    function projectHasPreferenceData(projectPreferences) {
        return Array.isArray(projectPreferences.layout) && projectPreferences.layout.length > 0
            || Array.isArray(projectPreferences.visibility) && projectPreferences.visibility.length > 0
            || Array.isArray(projectPreferences.colors) && projectPreferences.colors.length > 0;
    }

    function hasAnyStoredPreferences(preferences) {
        return Boolean(String(preferences && preferences.defaultProject || "").trim())
            || Object.keys(preferences && preferences.projects || {}).length > 0;
    }

    function maybeMigrateLegacyPreferences() {
        if (state.legacyPreferencesMigrated) return;
        state.legacyPreferencesMigrated = true;
        const legacyPreferences = buildLegacyPreferencesFromLocalStorage();
        if (!legacyPreferences) return;
        const mergedPreferences = mergePreferencePayloads(state.preferences, legacyPreferences);
        if (JSON.stringify(mergedPreferences) === JSON.stringify(state.preferences)) return;
        state.preferences = mergedPreferences;
        queuePreferencesSave();
    }

    function buildLegacyPreferencesFromLocalStorage() {
        const legacyPreferences = {
            defaultProject: readLegacyDefaultProject(),
            projects: {},
        };
        state.projects.forEach(function (project) {
            const projectId = normalizePreferenceKey(project && project.id);
            if (!projectId) return;
            const projectPreferences = readLegacyProjectPreferences(projectId);
            if (!projectHasPreferenceData(projectPreferences)) return;
            legacyPreferences.projects[projectId] = projectPreferences;
        });
        const normalized = normalizePreferences(legacyPreferences);

        return hasAnyStoredPreferences(normalized) ? normalized : null;
    }

    function mergePreferencePayloads(currentPreferences, legacyPreferences) {
        const merged = normalizePreferences(currentPreferences);
        const fallback = normalizePreferences(legacyPreferences);
        if (!merged.defaultProject) {
            merged.defaultProject = fallback.defaultProject;
        }
        Object.keys(fallback.projects).forEach(function (projectId) {
            if (!merged.projects[projectId]) {
                merged.projects[projectId] = fallback.projects[projectId];
                return;
            }
            const currentProject = normalizeProjectPreferences(merged.projects[projectId]);
            const fallbackProject = normalizeProjectPreferences(fallback.projects[projectId]);
            const nextProject = {
                layout: currentProject.layout.length ? currentProject.layout : fallbackProject.layout,
                visibility: currentProject.visibility.length ? currentProject.visibility : fallbackProject.visibility,
                colors: currentProject.colors.length ? currentProject.colors : fallbackProject.colors,
            };
            if (projectHasPreferenceData(nextProject)) {
                merged.projects[projectId] = nextProject;
            }
        });

        return normalizePreferences(merged);
    }

    function readLegacyProjectPreferences(projectId) {
        return normalizeProjectPreferences({
            layout: getCachedPayload(keys.legacyLayout + projectId, ttl.layout),
            visibility: getCachedPayload(keys.legacyVisibility + projectId, ttl.layout),
            colors: getCachedPayload(keys.legacyColors + projectId, ttl.layout),
        });
    }

    function readLegacyDefaultProject() {
        try {
            return String(window.localStorage.getItem(keys.legacyDefaultProject) || window.localStorage.getItem("defaultProject") || "").trim();
        } catch (error) {
            return "";
        }
    }

    function saveLegacyDefaultProject(projectId) {
        try {
            window.localStorage.setItem(keys.legacyDefaultProject, projectId);
            window.localStorage.setItem("defaultProject", projectId);
        } catch (error) {
            /* no-op */
        }
    }

    function setDefaultProjectPreference(projectId) {
        const normalizedProjectId = normalizePreferenceKey(projectId);
        if (!normalizedProjectId || state.preferences.defaultProject === normalizedProjectId) return;
        state.preferences.defaultProject = normalizedProjectId;
        queuePreferencesSave();
    }

    function getProjectPreferenceState(projectId, createIfMissing) {
        const normalizedProjectId = normalizePreferenceKey(projectId);
        if (!normalizedProjectId) return null;
        if (!state.preferences.projects[normalizedProjectId] && createIfMissing) {
            state.preferences.projects[normalizedProjectId] = { layout: [], visibility: [], colors: [] };
        }

        return state.preferences.projects[normalizedProjectId] || null;
    }

    function saveProjectPreferenceCards(projectId, section, cards) {
        const normalizedProjectId = normalizePreferenceKey(projectId);
        const projectPreferences = getProjectPreferenceState(normalizedProjectId, true);
        if (!projectPreferences) return;
        projectPreferences[section] = normalizeProjectPreferences({ [section]: cards })[section];
        if (!projectHasPreferenceData(projectPreferences) && state.preferences.defaultProject !== normalizedProjectId) {
            delete state.preferences.projects[normalizedProjectId];
        }
        queuePreferencesSave();
    }

    function getProjectPreferenceCards(projectId, section) {
        const projectPreferences = getProjectPreferenceState(projectId, false);
        if (!projectPreferences || !Array.isArray(projectPreferences[section])) {
            return [];
        }

        return projectPreferences[section];
    }

    function queuePreferencesSave() {
        if (!preferencesUrl || !preferencesCsrfToken) return;
        state.preferencesSaveQueued = true;
        if (state.preferencesSaveTimer) {
            window.clearTimeout(state.preferencesSaveTimer);
        }
        updateSaveStatus("Enregistrement...", "saving");
        state.preferencesSaveTimer = window.setTimeout(function () {
            state.preferencesSaveTimer = 0;
            flushPreferencesSave();
        }, 250);
    }

    function flushPreferencesSave() {
        if (!state.preferencesSaveQueued || state.preferencesSaveInFlight || !preferencesUrl || !preferencesCsrfToken) return;
        const payload = normalizePreferences(state.preferences);
        state.preferences = payload;
        state.preferencesSaveQueued = false;
        state.preferencesSaveInFlight = true;
        updateSaveStatus("Enregistrement...", "saving");
        postJson(preferencesUrl, { preferences: payload }, { "X-CSRF-Token": preferencesCsrfToken })
            .then(function (responsePayload) {
                state.preferences = normalizePreferences(responsePayload.preferences || payload);
                state.preferencesSaveInFlight = false;
                updateSaveStatus("Personnalisation sauvegardee.", "success");
                if (state.preferencesSaveQueued) {
                    flushPreferencesSave();
                }
            })
            .catch(function (error) {
                state.preferencesSaveInFlight = false;
                updateSaveStatus(error.message || "Sauvegarde impossible.", "error");
                if (window.console && typeof window.console.error === "function") {
                    window.console.error("Impossible de sauvegarder les preferences stats.", error);
                }
            });
    }

    function updateSaveStatus(message, tone) {
        if (!dom.statsSaveStatus) return;
        if (state.preferencesStatusTimer) {
            window.clearTimeout(state.preferencesStatusTimer);
            state.preferencesStatusTimer = 0;
        }
        dom.statsSaveStatus.textContent = message || "";
        dom.statsSaveStatus.hidden = !message;
        dom.statsSaveStatus.classList.remove("is-error", "is-success");
        if (tone === "error") {
            dom.statsSaveStatus.classList.add("is-error");
            return;
        }
        if (tone === "success") {
            dom.statsSaveStatus.classList.add("is-success");
            state.preferencesStatusTimer = window.setTimeout(function () {
                if (state.preferencesSaveInFlight || state.preferencesSaveQueued) return;
                dom.statsSaveStatus.textContent = "";
                dom.statsSaveStatus.hidden = true;
                dom.statsSaveStatus.classList.remove("is-success");
            }, 2200);
        }
    }

    function getCachedPayload(key, ttlMs) {
        try {
            const raw = window.localStorage.getItem(key);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object") return null;
            if (Date.now() - Number(parsed.cachedAt || 0) > ttlMs) return null;
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function savePayloadToCache(key, payload) {
        try {
            window.localStorage.setItem(key, JSON.stringify(Object.assign({}, payload, { cachedAt: Date.now() })));
            cleanupOldSelections();
        } catch (error) {
            /* no-op */
        }
    }

    function cleanupOldSelections() {
        Object.keys(window.localStorage).forEach(function (key) {
            if (!key.startsWith(keys.selection)) return;
            if (getCachedPayload(key, ttl.selection) !== null) return;
            window.localStorage.removeItem(key);
        });
    }

    function fetchJson(url) {
        return fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then(function (response) {
                return response.json().catch(function () { return { _error: "Reponse JSON invalide." }; });
            })
            .then(function (payload) {
                if (payload && payload._error) throw new Error(payload._detail || payload._error);
                return payload;
            });
    }

    function postJson(url, payload, extraHeaders) {
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: Object.assign({
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
            }, extraHeaders || {}),
            body: JSON.stringify(payload || {}),
        }).then(function (response) {
            return response.json().catch(function () { return { _error: "Reponse JSON invalide." }; })
                .then(function (responsePayload) {
                    if (!response.ok || responsePayload && responsePayload._error) {
                        throw new Error(responsePayload && (responsePayload._detail || responsePayload._error) || "Erreur de sauvegarde.");
                    }

                    return responsePayload;
                });
        });
    }

    function getUrlParameter(name) {
        const params = new URLSearchParams(window.location.search);
        return String(params.get(name) || "");
    }

    function fractionToPercent(fraction) {
        const parts = String(fraction || "1/8").split("/");
        const numerator = Number(parts[0] || 1);
        const denominator = Number(parts[1] || 8) || 8;
        return Math.max(12.5, Math.min(100, (numerator / denominator) * 100));
    }

    function scheduleLayout() {
        if (!dom.cardsGrid) return;
        if (state.layoutFrame) {
            window.cancelAnimationFrame(state.layoutFrame);
        }
        state.layoutFrame = window.requestAnimationFrame(function () {
            state.layoutFrame = 0;
            applyGridLayout();
        });
    }

    function dndComputeSlots(grid) {
        const cards = Array.from(grid.querySelectorAll(".card:not(.dragging):not(.is-dragging)"));
        const gridRect = grid.getBoundingClientRect();
        const slots = [];

        if (!cards.length) {
            slots.push({
                x: gridRect.left + gridRect.width / 2,
                y: gridRect.top + 20,
                height: 60,
                beforeEl: null,
            });
            return slots;
        }

        const lines = [];
        let currentLine = null;
        const yTolerance = 15;
        const cardRects = cards.map(function (card) {
            return { card: card, rect: card.getBoundingClientRect() };
        }).sort(function (left, right) {
            if (Math.abs(left.rect.top - right.rect.top) < yTolerance) {
                return left.rect.left - right.rect.left;
            }
            return left.rect.top - right.rect.top;
        });

        cardRects.forEach(function (entry) {
            if (!currentLine || Math.abs(entry.rect.top - currentLine.y) > yTolerance) {
                currentLine = { y: entry.rect.top, cards: [] };
                lines.push(currentLine);
            }
            currentLine.cards.push(entry);
        });

        lines.forEach(function (line, lineIndex) {
            line.cards.forEach(function (entry, index) {
                let slotX;
                if (index === 0) {
                    slotX = entry.rect.left - 4;
                } else {
                    const prevRect = line.cards[index - 1].rect;
                    slotX = (prevRect.right + entry.rect.left) / 2;
                }

                slots.push({
                    x: slotX,
                    y: entry.rect.top,
                    height: entry.rect.height,
                    beforeEl: entry.card,
                });
            });

            const lastRect = line.cards[line.cards.length - 1].rect;
            const afterLastBeforeEl = lineIndex < lines.length - 1 ? lines[lineIndex + 1].cards[0].card : null;
            slots.push({
                x: lastRect.right + 4,
                y: lastRect.top,
                height: lastRect.height,
                beforeEl: afterLastBeforeEl,
            });
        });

        return slots;
    }

    function dndFindClosestSlot(slots, mouseX, mouseY) {
        let closest = null;
        let minDist = Infinity;
        slots.forEach(function (slot) {
            const slotCenterY = slot.y + slot.height / 2;
            const dy = Math.abs(mouseY - slotCenterY);
            const dx = Math.abs(mouseX - slot.x);
            const isOnLine = mouseY >= slot.y - 20 && mouseY <= slot.y + slot.height + 20;
            const dist = isOnLine ? dx + dy * 0.3 : dx * 0.5 + dy;
            if (dist < minDist) {
                minDist = dist;
                closest = slot;
            }
        });
        return closest;
    }

    function dndComputeAndShow(grid, clientX, clientY) {
        const slots = dndComputeSlots(grid);
        if (!slots.length) return;
        const closest = dndFindClosestSlot(slots, clientX, clientY);
        if (!closest) return;

        if (closest.beforeEl === state.draggingCard) return;
        if (closest.beforeEl === null) {
            const visibleCards = Array.from(grid.querySelectorAll(".card:not(.dragging):not(.is-dragging)"));
            if (visibleCards.length > 0) {
                const nextSibling = findNextVisibleSibling(state.draggingCard);
                if (!nextSibling) return;
            }
        } else if (findNextVisibleSibling(state.draggingCard) === closest.beforeEl) {
            return;
        }

        state.insertBeforeEl = closest.beforeEl;
        dndShowIndicator(grid, closest);
    }

    function dndShowIndicator(grid, slot) {
        if (!state.dndIndicator) {
            state.dndIndicator = document.createElement("div");
            state.dndIndicator.className = "dnd-insertion-indicator";
            grid.appendChild(state.dndIndicator);
        }

        const gridRect = grid.getBoundingClientRect();
        const left = slot.x - gridRect.left + (grid.scrollLeft || 0) - 2;
        const top = slot.y - gridRect.top + (grid.scrollTop || 0) - 4;
        const height = slot.height + 8;

        state.dndIndicator.style.left = left + "px";
        state.dndIndicator.style.top = top + "px";
        state.dndIndicator.style.height = height + "px";
        state.dndIndicator.classList.add("visible");

        dndClearAdjacentFeedback();
        if (slot.beforeEl) {
            slot.beforeEl.classList.add("dnd-adjacent-after");
            const prev = findPrevVisibleSibling(slot.beforeEl);
            if (prev && prev !== state.draggingCard) {
                prev.classList.add("dnd-adjacent-before");
            }
        }
    }

    function dndHideIndicator() {
        if (state.dndIndicator) {
            state.dndIndicator.classList.remove("visible");
        }
        dndClearAdjacentFeedback();
    }

    function dndClearAdjacentFeedback() {
        document.querySelectorAll(".dnd-adjacent-before, .dnd-adjacent-after").forEach(function (element) {
            element.classList.remove("dnd-adjacent-before", "dnd-adjacent-after");
        });
    }

    function findNextVisibleSibling(element) {
        let nextSibling = element ? element.nextElementSibling : null;
        while (nextSibling && !nextSibling.classList.contains("card")) {
            nextSibling = nextSibling.nextElementSibling;
        }
        return nextSibling;
    }

    function findPrevVisibleSibling(element) {
        let prevSibling = element ? element.previousElementSibling : null;
        while (prevSibling && !prevSibling.classList.contains("card")) {
            prevSibling = prevSibling.previousElementSibling;
        }
        return prevSibling;
    }

    function applyGridLayout() {
        if (!dom.cardsGrid) return;
        const gridWidth = dom.cardsGrid.offsetWidth || 0;
        const gap = parseFloat(window.getComputedStyle(dom.cardsGrid).gap) || 16;
        if (!gridWidth) return;

        let currentLineWidth = 0;
        Array.from(dom.cardsGrid.querySelectorAll(".card")).forEach(function (card) {
            const fraction = String(card.getAttribute("data-card-fraction") || "1/8");
            const percentValue = fractionToPercent(fraction) / 100;
            const cardWidth = Math.floor(gridWidth * percentValue);

            if (currentLineWidth > 0 && currentLineWidth + gap + cardWidth > gridWidth) {
                currentLineWidth = 0;
            }

            applyCardFraction(card, fraction);
            currentLineWidth += cardWidth + gap;
        });
    }

    function getPrevFraction(current) {
        const index = Math.max(0, fractions.indexOf(current));
        return fractions[Math.max(0, index - 1)];
    }

    function getNextFraction(current) {
        const index = Math.max(0, fractions.indexOf(current));
        return fractions[Math.min(fractions.length - 1, index + 1)];
    }

    function localeSort(left, right) {
        return String(left || "").localeCompare(String(right || ""), "fr", { sensitivity: "base" });
    }

    function slugify(value) {
        return String(value || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
    }

    function normalizeColorInputValue(value) {
        const color = String(value || "").trim();
        if (!color) return "#ffffff";
        if (color.startsWith("#")) {
            if (color.length === 4) {
                return "#" + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
            }
            return color.slice(0, 7);
        }
        const match = color.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
        if (!match) return "#ffffff";
        return "#" + [match[1], match[2], match[3]].map(function (part) {
            return Number(part).toString(16).padStart(2, "0");
        }).join("");
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === "function") {
            return window.CSS.escape(value);
        }
        return String(value || "").replace(/["\\]/g, "\\$&");
    }

    function escapeHtml(value) {
        const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
        return String(value || "").replace(/[&<>"']/g, function (match) { return map[match]; });
    }
});
