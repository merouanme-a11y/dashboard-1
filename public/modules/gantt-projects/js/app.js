const STORAGE_KEY = "adep-gantt-planner-state-v1";
const GANTT_CONFIG = window.__GANTT_CONFIG__ || {};
const GANTT_ROUTE_CONFIG = GANTT_CONFIG.routes || {};
const GANTT_BASE_URL = String(GANTT_CONFIG.baseUrl || "./gantt-projets").replace(/\/$/, "");
const GANTT_LOGIN_URL = String(GANTT_CONFIG.loginUrl || "./login.php");
const GANTT_LOGOUT_URL = String(GANTT_CONFIG.logoutUrl || "./logout.php");
const GANTT_TICKET_DETAIL_URL_PATTERN = String(
    GANTT_CONFIG.ticketDetailUrlPattern || `${window.location.origin}/tickets/__ID__`
);
const API_ROUTES = {
    session: String(GANTT_ROUTE_CONFIG.session || `${GANTT_BASE_URL}/api/session`),
    login: String(GANTT_ROUTE_CONFIG.login || `${GANTT_BASE_URL}/api/login`),
    logout: String(GANTT_ROUTE_CONFIG.logout || `${GANTT_BASE_URL}/api/logout`),
    projects: String(GANTT_ROUTE_CONFIG.projects || `${GANTT_BASE_URL}/api/projects`),
    createProject: String(GANTT_ROUTE_CONFIG.createProject || `${GANTT_BASE_URL}/api/create-project`),
    projectUsers: String(GANTT_ROUTE_CONFIG.projectUsers || `${GANTT_BASE_URL}/api/project-users`),
    projectTeam: String(GANTT_ROUTE_CONFIG.projectTeam || `${GANTT_BASE_URL}/api/project-team`),
    youtrackProjectTasks: String(GANTT_ROUTE_CONFIG.youtrackProjectTasks || `${GANTT_BASE_URL}/api/youtrack-project-tasks`),
    createYouTrackProjectTask: String(GANTT_ROUTE_CONFIG.createYouTrackProjectTask || `${GANTT_BASE_URL}/api/create-youtrack-project-task`),
    updateYouTrackProjectTask: String(GANTT_ROUTE_CONFIG.updateYouTrackProjectTask || `${GANTT_BASE_URL}/api/update-youtrack-project-task`),
    deleteYouTrackProjectTask: String(GANTT_ROUTE_CONFIG.deleteYouTrackProjectTask || `${GANTT_BASE_URL}/api/delete-youtrack-project-task`),
    services: String(GANTT_ROUTE_CONFIG.services || `${GANTT_BASE_URL}/api/services`),
    importProjects: String(GANTT_ROUTE_CONFIG.importProjects || `${GANTT_BASE_URL}/api/import-projects`),
    exportProjects: String(GANTT_ROUTE_CONFIG.exportProjects || `${GANTT_BASE_URL}/api/export-projects`)
};

const DEFAULT_SETTINGS = {
    timelineStart: "2026-01",
    visibleMonths: 12,
    monthWidth: 150,
    timelineZoom: 1,
    defaultDuration: 1,
    showTodayMarker: true,
    showTimelineProgress: true,
    showPlanningSidebar: true,
    showSettingsPanel: true,
    serviceFilter: "all",
    typeFilter: "all",
    statusFilter: "all",
    backlogView: "cards",
    search: "",
    expandedProjectIds: []
};

const PROJECT_STATUSES = [
    { value: "A planifier", className: "is-to-plan" },
    { value: "Planifié", className: "is-planned" },
    { value: "En cours", className: "is-in-progress" },
    { value: "Terminé", className: "is-done" },
    { value: "Standby", className: "is-standby" }
];
const PROJECT_TYPES = [
    "Maintenance",
    "Evolution",
    "Projet transverse",
    "Projet non transverse"
];
const PROJECT_MODAL_DEFAULT_HELP = "La timeline reste alignée sur des demi-mois, mais vous pouvez piloter ici les dates de début et de fin du projet.";
const TIMELINE_ROW_REORDER_MIME = "application/x-gantt-row-reorder";
const EMPTY_PROJECT_TYPE_FILTER = "__empty__";

const DEFAULT_PROJECT_TASK_COLUMNS = ["idReadable", "summary", "assignee", "dueDate", "state"];
const PROJECT_TASK_COLUMN_OPTIONS = [
    { key: "idReadable", label: "ID" },
    { key: "summary", label: "Résumé" },
    { key: "assignee", label: "Responsable" },
    { key: "dueDate", label: "Date échéance" },
    { key: "state", label: "État" }
];
const FRONTEND_CACHE_TTLS = {
    projects: 15_000,
    services: 60_000,
    projectUsers: 300_000,
    projectTeam: 30_000,
    youTrackProjectTasks: 30_000
};

const SERVICE_COLORS = {
    "Comptabilite": "#ab3df5",
    "Relation Client": "#b95ad3",
    "Marketing": "#4da5f7",
    "Communication": "#3d3d38",
    "Conformite": "#d415c4",
    "Prestations": "#56bad3",
    "Production": "#ffbb00",
    "IT": "#5380e0",
    "Controle Interne": "#da1414"
};

const LEGACY_SERVICE_COLORS = {
    "Comptabilite": "#ab3df5",
    "Relation Client": "#b95ad3",
    "Marketing": "#4da5f7",
    "Communication": "#3d3d38",
    "Conformite": "#d415c4",
    "Prestations": "#56bad3",
    "Production": "#ffbb00",
    "IT": "#5380e0",
    "Controle Interne": "#da1414"
};

const dom = {
    root: document.querySelector("#ganttDashboardPage"),
    timelinePanel: document.querySelector("#timelinePanel"),
    projectModal: document.querySelector("#projectModal"),
    projectModalClose: document.querySelector("#projectModalClose"),
    projectModalForm: document.querySelector("#projectModalForm"),
    projectModalYouTrackBadge: document.querySelector("#projectModalYouTrackBadge"),
    projectModalKicker: document.querySelector("#projectModalKicker"),
    projectModalTitle: document.querySelector("#projectModalTitle"),
    projectModalTitleInput: document.querySelector("#projectModalTitleInput"),
    projectModalYouTrackBlock: document.querySelector("#projectModalYouTrackBlock"),
    projectModalCreateInYouTrack: document.querySelector("#projectModalCreateInYouTrack"),
    projectModalYouTrackNote: document.querySelector("#projectModalYouTrackNote"),
    projectModalDescriptionToggle: document.querySelector("#projectModalDescriptionToggle"),
    projectModalDescriptionToggleIcon: document.querySelector("#projectModalDescriptionToggleIcon"),
    projectModalDescriptionBlock: document.querySelector("#projectModalDescriptionBlock"),
    projectModalTeamMenu: document.querySelector("#projectModalTeamMenu"),
    projectModalTeamBadges: document.querySelector("#projectModalTeamBadges"),
    projectModalRefDisplay: document.querySelector("#projectModalRefDisplay"),
    projectModalRefInput: document.querySelector("#projectModalRefInput"),
    projectModalServiceDisplay: document.querySelector("#projectModalServiceDisplay"),
    projectModalServiceInput: document.querySelector("#projectModalServiceInput"),
    projectModalTypeDisplay: document.querySelector("#projectModalTypeDisplay"),
    projectModalTypeInput: document.querySelector("#projectModalTypeInput"),
    projectModalParentDisplay: document.querySelector("#projectModalParentDisplay"),
    projectModalParentInput: document.querySelector("#projectModalParentInput"),
    projectModalStartDisplay: document.querySelector("#projectModalStartDisplay"),
    projectModalStartInput: document.querySelector("#projectModalStartInput"),
    projectModalEndDisplay: document.querySelector("#projectModalEndDisplay"),
    projectModalEndInput: document.querySelector("#projectModalEndInput"),
    projectModalColorDisplay: document.querySelector("#projectModalColorDisplay"),
    projectModalColorInput: document.querySelector("#projectModalColorInput"),
    projectModalColorHexInput: document.querySelector("#projectModalColorHexInput"),
    projectModalRiskGainDisplay: document.querySelector("#projectModalRiskGainDisplay"),
    projectModalRiskGainInput: document.querySelector("#projectModalRiskGainInput"),
    projectModalBudgetDisplay: document.querySelector("#projectModalBudgetDisplay"),
    projectModalBudgetInput: document.querySelector("#projectModalBudgetInput"),
    projectModalPrioritizationDisplay: document.querySelector("#projectModalPrioritizationDisplay"),
    projectModalPrioritizationInput: document.querySelector("#projectModalPrioritizationInput"),
    projectModalStatusDisplay: document.querySelector("#projectModalStatusDisplay"),
    projectModalStatusInput: document.querySelector("#projectModalStatusInput"),
    projectModalProgressDisplay: document.querySelector("#projectModalProgressDisplay"),
    projectModalProgressInput: document.querySelector("#projectModalProgressInput"),
    projectModalYouTrackTasks: document.querySelector("#projectModalYouTrackTasks"),
    projectModalYouTrackTasksCount: document.querySelector("#projectModalYouTrackTasksCount"),
    projectModalYouTrackColumnsMenu: document.querySelector("#projectModalYouTrackColumnsMenu"),
    projectModalYouTrackTaskToggle: document.querySelector("#projectModalYouTrackTaskToggle"),
    projectModalYouTrackTasksBody: document.querySelector("#projectModalYouTrackTasksBody"),
    projectModalHelp: document.querySelector("#projectModalHelp"),
    projectModalError: document.querySelector("#projectModalError"),
    projectModalDeleteButton: document.querySelector("#projectModalDeleteButton"),
    projectModalClearButton: document.querySelector("#projectModalClearButton"),
    projectModalSubmitButton: document.querySelector("#projectModalSubmitButton"),
    projectModalDescription: document.querySelector("#projectModalDescription"),
    projectModalDescriptionInput: document.querySelector("#projectModalDescriptionInput"),
    scheduledCount: document.querySelector("#scheduledCount"),
    backlogCount: document.querySelector("#backlogCount"),
    rangeSummary: document.querySelector("#rangeSummary"),
    timelineStart: document.querySelector("#timelineStart"),
    visibleMonths: document.querySelector("#visibleMonths"),
    visibleMonthsValue: document.querySelector("#visibleMonthsValue"),
    monthWidth: document.querySelector("#monthWidth"),
    monthWidthValue: document.querySelector("#monthWidthValue"),
    serviceFilter: document.querySelector("#serviceFilter"),
    typeFilter: document.querySelector("#typeFilter"),
    statusFilter: document.querySelector("#statusFilter"),
    searchInput: document.querySelector("#searchInput"),
    addProjectButton: document.querySelector("#addProjectButton"),
    serviceColorSelect: document.querySelector("#serviceColorSelect"),
    serviceColorInput: document.querySelector("#serviceColorInput"),
    controlsPanel: document.querySelector("#controlsPanel"),
    timelineBoard: document.querySelector("#timelineBoard"),
    timelineYears: document.querySelector("#timelineYears"),
    timelineMonths: document.querySelector("#timelineMonths"),
    timelineRows: document.querySelector("#timelineRows"),
    projectPool: document.querySelector("#projectPool"),
    toggleBacklogViewButton: document.querySelector("#toggleBacklogViewButton"),
    backlogNote: document.querySelector("#backlogNote"),
    importSourceInput: document.querySelector("#importSourceInput"),
    importSourceButton: document.querySelector("#importSourceButton"),
    todayButton: document.querySelector("#todayButton"),
    exportButton: document.querySelector("#exportButton"),
    resetButton: document.querySelector("#resetButton"),
    togglePlanningSidebarButton: document.querySelector("#togglePlanningSidebarButton"),
    timelineZoomOutButton: document.querySelector("#timelineZoomOutButton"),
    timelineZoomInButton: document.querySelector("#timelineZoomInButton"),
    timelineZoomValue: document.querySelector("#timelineZoomValue"),
    toggleTodayMarkerButton: document.querySelector("#toggleTodayMarkerButton"),
    toggleTimelineProgressButton: document.querySelector("#toggleTimelineProgressButton"),
    toggleSettingsPanelButton: document.querySelector("#toggleSettingsPanelButton"),
    focusPlanningButton: document.querySelector("#focusPlanningButton"),
    exitPlanningFocusButton: document.querySelector("#exitPlanningFocusButton"),
    authOverlay: document.querySelector("#authOverlay"),
    loginForm: document.querySelector("#loginForm"),
    loginUsername: document.querySelector("#loginUsername"),
    loginPassword: document.querySelector("#loginPassword"),
    loginError: document.querySelector("#loginError"),
    authUser: document.querySelector("#authUser"),
    logoutButton: document.querySelector("#logoutButton"),
    themeToggleButton: document.querySelector(".dark-mode-toggle")
};

const state = {
    settings: { ...DEFAULT_SETTINGS },
    projects: [],
    projectUsers: [],
    currentUser: null,
    serviceColors: { ...SERVICE_COLORS },
    selectedServiceColor: ""
};
const apiMemoryCache = {
    values: new Map(),
    inflight: new Map(),
    revisions: new Map()
};

const monthShortFormatter = new Intl.DateTimeFormat("fr-FR", { month: "short" });
const monthLongFormatter = new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" });
const fullDateFormatter = new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "long", year: "numeric" });
const TIMELINE_ZOOM_STEP = 0.1;
const TIMELINE_ZOOM_MIN = 0.7;
const TIMELINE_ZOOM_MAX = 1.5;
let interaction = null;
let timelineRowReorder = null;
let appStarted = false;
let projectsSyncTimeout = null;
let projectsSyncInFlight = false;
let projectsSyncQueued = false;
let themeObserver = null;
let projectModalTasksRequestToken = 0;
let projectModalTeamRequestToken = 0;
const projectModalYouTrackTasksState = {
    mode: "hidden",
    projectKey: "",
    tasks: [],
    assignees: [],
    stateOptions: [],
    customFieldColumns: [],
    pendingTasks: [],
    draftActive: false,
    draftSummary: "",
    draftAssigneeId: "",
    draftDueDate: "",
    draftState: "",
    draftCustomFieldValues: {},
    draftSubmitting: false,
    draftError: "",
    editingTaskId: "",
    editingField: "",
    editingValue: "",
    editingSubmitting: false,
    editingError: "",
};
const projectModalTeamState = {
    members: [],
    canManage: false,
    filterQuery: "",
};
const projectModalTaskColumnsState = {
    columns: [...DEFAULT_PROJECT_TASK_COLUMNS],
};

init();

function ensureProjectModalEnhancements() {
    if (dom.projectModalCreateInYouTrack) {
        const toggleLabel = dom.projectModalCreateInYouTrack.parentElement?.querySelector("span:last-child");
        if (toggleLabel) {
            toggleLabel.textContent = "Projet Youtrack";
        }
    }
}

function getRootElement() {
    return dom.root;
}

function toggleRootClass(className, force) {
    const root = getRootElement();
    if (root) {
        root.classList.toggle(className, force);
    }
}

function addRootClass(className) {
    const root = getRootElement();
    if (root) {
        root.classList.add(className);
    }
}

function removeRootClass(className) {
    const root = getRootElement();
    if (root) {
        root.classList.remove(className);
    }
}

function rootHasClass(className) {
    const root = getRootElement();
    return root ? root.classList.contains(className) : false;
}

function setPlanningLayoutHidden(hidden) {
    document.documentElement.classList.toggle("gantt-planning-layout-hidden", hidden);
    document.body.classList.toggle("gantt-planning-layout-hidden", hidden);
}

async function requestPlanningFullscreen() {
    if (!(dom.root instanceof HTMLElement) || typeof dom.root.requestFullscreen !== "function") {
        return false;
    }

    try {
        await dom.root.requestFullscreen({ navigationUI: "hide" });
        return true;
    } catch (error) {
        try {
            await dom.root.requestFullscreen();
            return true;
        } catch (fallbackError) {
            console.warn("Impossible d'activer le plein écran du planning.", fallbackError);
            return false;
        }
    }
}

function onPlanningFullscreenChange() {
    if (!document.fullscreenElement && rootHasClass("planning-focus-mode")) {
        removeRootClass("planning-focus-mode");
        setPlanningLayoutHidden(false);
    }

    syncPlanningFocusUi();
}

async function init() {
    syncThemeFromTemplate();
    observeTemplateTheme();
    ensureProjectModalEnhancements();
    bindStaticEvents();
    bindAuthEvents();
    syncPlanningFocusUi();

    const bootstrapUser = window.__GANTT_AUTH_USER__ || null;
    if (bootstrapUser?.username) {
        unlockApplication(bootstrapUser);
        await startApplication();
        return;
    }

    const session = await loadSession();
    if (!session?.user) {
        window.location.replace(GANTT_LOGIN_URL);
        return;
    }

    unlockApplication(session.user);
    await startApplication();
}

async function startApplication() {
    if (appStarted) {
        render();
        return;
    }

    appStarted = true;

    try {
        const [seedProjects, servicesPayload, projectUsersPayload] = await Promise.all([
            loadProjects(),
            loadServices().catch(() => ({ services: {} })),
            loadProjectUsers().catch(() => ({ users: [] }))
        ]);
        applyServiceColors(servicesPayload?.services || {});
        state.projectUsers = Array.isArray(projectUsersPayload?.users) ? projectUsersPayload.users : [];
        hydrateState(seedProjects, { preferSeedPlanning: true });
        render();
    } catch (error) {
        console.error(error);
        dom.timelineRows.innerHTML = `<div class="timeline-empty">Impossible de charger les projets. Vérifiez la session utilisateur et les endpoints PHP.</div>`;
        dom.projectPool.innerHTML = `<div class="pool-empty">Le chargement des projets a échoué.</div>`;
    }
}

function bindAuthEvents() {
    if (dom.loginForm) {
        dom.loginForm.addEventListener("submit", onLoginSubmit);
    }

    if (dom.logoutButton) {
        dom.logoutButton.addEventListener("click", logout);
    }
}

function applyTheme(theme) {
    const isDark = theme !== "light";
    toggleRootClass("theme-dark", isDark);
    toggleRootClass("theme-light", !isDark);
}

function syncThemeFromTemplate() {
    const theme = document.documentElement.classList.contains("dark") ? "dark" : "light";
    applyTheme(theme);
}

function observeTemplateTheme() {
    if (themeObserver || typeof MutationObserver === "undefined") {
        return;
    }

    themeObserver = new MutationObserver(() => {
        syncThemeFromTemplate();
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["class"]
    });
}

async function onLoginSubmit(event) {
    event.preventDefault();

    const username = dom.loginUsername.value.trim();
    const password = dom.loginPassword.value;

    try {
        const session = await apiRequest(API_ROUTES.login, {
            method: "POST",
            body: JSON.stringify({ username, password })
        });

        unlockApplication(session.user);
        await startApplication();
    } catch (error) {
        console.error(error);
        dom.loginError.hidden = false;
        dom.loginPassword.value = "";
        dom.loginPassword.focus();
    }
}

function lockApplication() {
    state.currentUser = null;
    window.location.replace(GANTT_LOGIN_URL);
}

function unlockApplication(user) {
    state.currentUser = user && typeof user === "object"
        ? {
            ...user,
            id: String(user.id || user.username || "").trim(),
            username: String(user.username || user.id || "").trim(),
            displayName: String(user.displayName || user.username || user.id || "").trim(),
            email: String(user.email || "").trim(),
        }
        : null;
    removeRootClass("is-auth-locked");
    if (dom.authOverlay) {
        dom.authOverlay.hidden = true;
    }
    if (dom.loginError) {
        dom.loginError.hidden = true;
    }
    if (dom.authUser) {
        dom.authUser.textContent = user.displayName || user.username;
    }
}

async function logout() {
    closeProjectModal();
    await exitPlanningFocus();
    appStarted = false;
    window.location.href = GANTT_LOGOUT_URL;
}

async function loadSession() {
    try {
        return await apiRequest(API_ROUTES.session);
    } catch (error) {
        return null;
    }
}

function cloneApiCacheValue(value) {
    if (value === null || value === undefined) {
        return value;
    }

    if (typeof structuredClone === "function") {
        try {
            return structuredClone(value);
        } catch (error) {
        }
    }

    if (typeof value !== "object") {
        return value;
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch (error) {
        return value;
    }
}

function buildApiCacheKey(namespace, identifier = "default") {
    const normalizedNamespace = String(namespace || "default").trim() || "default";
    const normalizedIdentifier = String(identifier || "default").trim() || "default";
    return `${normalizedNamespace}::${normalizedIdentifier}`;
}

function getApiCacheRevision(cacheKey) {
    return Number(apiMemoryCache.revisions.get(cacheKey) || 0);
}

function setApiCacheValue(namespace, identifier, value) {
    const cacheKey = buildApiCacheKey(namespace, identifier);
    apiMemoryCache.values.set(cacheKey, {
        storedAt: Date.now(),
        value: cloneApiCacheValue(value)
    });
}

function invalidateApiCache(namespace, identifier = null) {
    const prefix = `${String(namespace || "default").trim() || "default"}::`;
    const cacheKeys = new Set([
        ...apiMemoryCache.values.keys(),
        ...apiMemoryCache.inflight.keys(),
        ...apiMemoryCache.revisions.keys()
    ]);

    for (const cacheKey of cacheKeys) {
        if (!cacheKey.startsWith(prefix)) {
            continue;
        }

        if (identifier !== null && cacheKey !== buildApiCacheKey(namespace, identifier)) {
            continue;
        }

        apiMemoryCache.values.delete(cacheKey);
        apiMemoryCache.inflight.delete(cacheKey);
        apiMemoryCache.revisions.set(cacheKey, getApiCacheRevision(cacheKey) + 1);
    }
}

async function rememberApiResponse(namespace, identifier, ttl, loader, options = {}) {
    const cacheKey = buildApiCacheKey(namespace, identifier);
    const force = Boolean(options.force);
    const now = Date.now();

    if (!force) {
        const cachedEntry = apiMemoryCache.values.get(cacheKey);
        if (cachedEntry && now - Number(cachedEntry.storedAt || 0) <= ttl) {
            return cloneApiCacheValue(cachedEntry.value);
        }

        if (apiMemoryCache.inflight.has(cacheKey)) {
            return cloneApiCacheValue(await apiMemoryCache.inflight.get(cacheKey));
        }
    }

    const requestRevision = getApiCacheRevision(cacheKey);
    const inflightRequest = Promise.resolve()
        .then(() => loader())
        .then((payload) => {
            if (getApiCacheRevision(cacheKey) === requestRevision) {
                setApiCacheValue(namespace, identifier, payload);
            }

            return cloneApiCacheValue(payload);
        })
        .finally(() => {
            if (apiMemoryCache.inflight.get(cacheKey) === inflightRequest) {
                apiMemoryCache.inflight.delete(cacheKey);
            }
        });

    apiMemoryCache.inflight.set(cacheKey, inflightRequest);
    return cloneApiCacheValue(await inflightRequest);
}

async function loadProjects() {
    return rememberApiResponse("projects", "all", FRONTEND_CACHE_TTLS.projects, () => apiRequest(API_ROUTES.projects));
}

async function loadServices() {
    return rememberApiResponse("services", "all", FRONTEND_CACHE_TTLS.services, () => apiRequest(API_ROUTES.services));
}

async function loadProjectUsers() {
    return rememberApiResponse("projectUsers", "all", FRONTEND_CACHE_TTLS.projectUsers, () => apiRequest(API_ROUTES.projectUsers));
}

async function loadYouTrackProjectTeam(projectKey) {
    const normalizedProjectKey = String(projectKey || "").trim();
    if (!normalizedProjectKey) {
        return { team: [], canManage: false };
    }

    const url = new URL(API_ROUTES.projectTeam, window.location.origin);
    url.searchParams.set("project", normalizedProjectKey);
    return rememberApiResponse("projectTeam", normalizedProjectKey, FRONTEND_CACHE_TTLS.projectTeam, () => apiRequest(url.toString()));
}

async function addYouTrackProjectTeamMember(projectKey, userId) {
    const normalizedProjectKey = String(projectKey || "").trim();
    const payload = await apiRequest(API_ROUTES.projectTeam, {
        method: "POST",
        body: JSON.stringify({ project: projectKey, userId })
    });

    invalidateApiCache("projectTeam", normalizedProjectKey);
    invalidateApiCache("youTrackProjectTasks", normalizedProjectKey);
    if (payload) {
        setApiCacheValue("projectTeam", normalizedProjectKey, payload);
    }

    return payload;
}

async function removeYouTrackProjectTeamMember(projectKey, userId) {
    const normalizedProjectKey = String(projectKey || "").trim();
    const payload = await apiRequest(API_ROUTES.projectTeam, {
        method: "DELETE",
        body: JSON.stringify({ project: projectKey, userId })
    });

    invalidateApiCache("projectTeam", normalizedProjectKey);
    invalidateApiCache("youTrackProjectTasks", normalizedProjectKey);
    if (payload) {
        setApiCacheValue("projectTeam", normalizedProjectKey, payload);
    }

    return payload;
}

async function deleteProject(projectId) {
    const payload = await apiRequest(API_ROUTES.projects, {
        method: "DELETE",
        body: JSON.stringify({ id: projectId })
    });

    invalidateApiCache("projects");
    invalidateApiCache("projectTeam");
    invalidateApiCache("youTrackProjectTasks");

    return payload;
}

async function createProjectRecord(project, createInYouTrack = false, removeFromYouTrack = false) {
    const oldProjectKey = getYouTrackProjectKey(project);
    const payload = await apiRequest(API_ROUTES.createProject, {
        method: "POST",
        body: JSON.stringify({
            project,
            createInYouTrack: Boolean(createInYouTrack),
            removeFromYouTrack: Boolean(removeFromYouTrack)
        })
    });

    invalidateApiCache("projects");
    if (oldProjectKey) {
        invalidateApiCache("projectTeam", oldProjectKey);
        invalidateApiCache("youTrackProjectTasks", oldProjectKey);
    }

    const newProjectKey = getYouTrackProjectKey(payload?.project || project);
    if (newProjectKey) {
        invalidateApiCache("projectTeam", newProjectKey);
        invalidateApiCache("youTrackProjectTasks", newProjectKey);
    }

    return payload;
}

async function loadYouTrackProjectTasks(projectKey) {
    const normalizedProjectKey = String(projectKey || "").trim();
    if (!normalizedProjectKey) {
        return { project: "", tasks: [], assignees: [], stateOptions: [], customFieldColumns: [] };
    }

    const url = new URL(API_ROUTES.youtrackProjectTasks, window.location.origin);
    url.searchParams.set("project", normalizedProjectKey);
    return rememberApiResponse("youTrackProjectTasks", normalizedProjectKey, FRONTEND_CACHE_TTLS.youTrackProjectTasks, () => apiRequest(url.toString()));
}

async function createYouTrackProjectTask(projectKey, task) {
    const normalizedProjectKey = String(projectKey || "").trim();
    const payload = await apiRequest(API_ROUTES.createYouTrackProjectTask, {
        method: "POST",
        body: JSON.stringify({
            project: projectKey,
            task
        })
    });

    invalidateApiCache("youTrackProjectTasks", normalizedProjectKey);
    return payload;
}

async function updateYouTrackProjectTask(projectKey, taskId, updates) {
    const normalizedProjectKey = String(projectKey || "").trim();
    const payload = await apiRequest(API_ROUTES.updateYouTrackProjectTask, {
        method: "POST",
        body: JSON.stringify({ project: projectKey, taskId, updates })
    });

    invalidateApiCache("youTrackProjectTasks", normalizedProjectKey);
    return payload;
}

async function deleteYouTrackProjectTask(projectKey, issueId) {
    const normalizedProjectKey = String(projectKey || "").trim();
    const payload = await apiRequest(API_ROUTES.deleteYouTrackProjectTask, {
        method: "DELETE",
        body: JSON.stringify({ project: projectKey, id: issueId })
    });

    invalidateApiCache("youTrackProjectTasks", normalizedProjectKey);
    return payload;
}

async function saveServiceColor(service, color) {
    const payload = await apiRequest(API_ROUTES.services, {
        method: "POST",
        body: JSON.stringify({ service, color })
    });

    invalidateApiCache("services");
    return payload;
}

async function onImportSourceFile(event) {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    dom.importSourceButton.disabled = true;
    dom.importSourceButton.textContent = "Import en cours...";

    try {
        const payload = await uploadSourceFile(file);
        const servicesPayload = await loadServices().catch(() => ({ services: {} }));
        applyServiceColors(servicesPayload?.services || {});
        hydrateState(payload.projects || [], { preferSeedPlanning: true });
        persistState();
        render();

        const summary = payload.summary || {};
        window.alert(
            `Import terminé.\n${summary.updatedCount || 0} projet(s) mis à jour.\n${summary.clearedCount || 0} projet(s) vidés.\n${summary.unmatchedCount || 0} ligne(s) non rapprochées.`
        );
    } catch (error) {
        console.error(error);
        window.alert(error.message || "L'import du fichier source a échoué.");
    } finally {
        dom.importSourceInput.value = "";
        dom.importSourceButton.disabled = false;
        dom.importSourceButton.textContent = "Importer source Excel";
    }
}

async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            ...(options.headers || {})
        },
        ...options
    });

    if (!response.ok) {
        let payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        throw new Error(payload?.message || `Requête impossible (${response.status})`);
    }

    if (response.status === 204) {
        return null;
    }

    return response.json();
}

async function uploadSourceFile(file) {
    const formData = new FormData();
    formData.append("sourceFile", file);

    const response = await fetch(API_ROUTES.importProjects, {
        method: "POST",
        credentials: "same-origin",
        body: formData
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch (error) {
        payload = null;
    }

    if (!response.ok) {
        throw new Error(payload?.message || `Import impossible (${response.status})`);
    }

    invalidateApiCache("projects");
    invalidateApiCache("services");

    return payload;
}

function clearApplicationData() {
    state.projects = [];
    state.serviceColors = { ...SERVICE_COLORS };
    state.selectedServiceColor = "";
    dom.scheduledCount.textContent = "0";
    dom.backlogCount.textContent = "0";
    dom.rangeSummary.textContent = `${DEFAULT_SETTINGS.visibleMonths} mois`;
    dom.timelineYears.innerHTML = "";
    dom.timelineMonths.innerHTML = "";
    dom.timelineRows.innerHTML = "";
    dom.projectPool.innerHTML = "";
    if (dom.backlogNote) {
        dom.backlogNote.textContent = "Connectez-vous pour accéder au planning.";
    }
}

function hydrateState(seedProjects, options = {}) {
    const savedState = readSavedState();
    const savedSettings = { ...(savedState.settings || {}) };

    // Force the drop default back to 1 month when an older browser state kept a larger value.
    if (Number(savedSettings.defaultDuration) !== DEFAULT_SETTINGS.defaultDuration) {
        savedSettings.defaultDuration = DEFAULT_SETTINGS.defaultDuration;
    }

    state.settings = { ...DEFAULT_SETTINGS, ...savedSettings };
    state.settings.backlogView = normalizeBacklogView(state.settings.backlogView);
    state.settings.expandedProjectIds = normalizeExpandedProjectIds(state.settings.expandedProjectIds);

    state.projects = seedProjects.map((project) => normalizeProjectForState(project));

    normalizeLanes();
    sanitizeExpandedProjectIds();
    populateServiceFilter();
    writeSerializableStateToStorage();
}

function bindStaticEvents() {
    if (dom.addProjectButton) {
        dom.addProjectButton.addEventListener("click", openCreateProjectModal);
    }

    dom.importSourceButton.addEventListener("click", () => dom.importSourceInput.click());
    dom.importSourceInput.addEventListener("change", onImportSourceFile);

    dom.timelineStart.addEventListener("change", () => {
        state.settings.timelineStart = dom.timelineStart.value || DEFAULT_SETTINGS.timelineStart;
        persistState();
        render();
    });

    dom.visibleMonths.addEventListener("input", () => {
        dom.visibleMonthsValue.textContent = `${dom.visibleMonths.value} mois`;
    });

    dom.visibleMonths.addEventListener("change", () => {
        state.settings.visibleMonths = Number(dom.visibleMonths.value);
        persistState();
        render();
    });

    dom.monthWidth.addEventListener("input", () => {
        dom.monthWidthValue.textContent = `${dom.monthWidth.value} px`;
    });

    dom.monthWidth.addEventListener("change", () => {
        state.settings.monthWidth = Number(dom.monthWidth.value);
        persistState();
        render();
    });

    dom.serviceFilter.addEventListener("change", () => {
        state.settings.serviceFilter = dom.serviceFilter.value;
        persistState();
        render();
    });

    dom.typeFilter.addEventListener("change", () => {
        state.settings.typeFilter = dom.typeFilter.value;
        persistState();
        render();
    });

    dom.statusFilter.addEventListener("change", () => {
        state.settings.statusFilter = dom.statusFilter.value;
        persistState();
        render();
    });

    if (dom.toggleBacklogViewButton) {
        dom.toggleBacklogViewButton.addEventListener("click", toggleBacklogView);
    }

    dom.serviceColorSelect.addEventListener("change", onServiceColorSelectionChange);
    dom.serviceColorInput.addEventListener("change", onServiceColorSave);

    dom.searchInput.addEventListener("input", () => {
        state.settings.search = dom.searchInput.value;
        persistState();
        render();
    });

    dom.todayButton.addEventListener("click", () => {
        state.settings.timelineStart = getCurrentYearMonth();
        persistState();
        render();
    });

    dom.exportButton.addEventListener("click", exportPlanning);
    dom.resetButton.addEventListener("click", resetPlanning);
    dom.togglePlanningSidebarButton.addEventListener("click", togglePlanningSidebar);
    dom.timelineZoomOutButton.addEventListener("click", () => adjustTimelineZoom(-TIMELINE_ZOOM_STEP));
    dom.timelineZoomInButton.addEventListener("click", () => adjustTimelineZoom(TIMELINE_ZOOM_STEP));
    dom.toggleTodayMarkerButton.addEventListener("click", toggleTodayMarker);
    dom.toggleTimelineProgressButton.addEventListener("click", toggleTimelineProgress);
    dom.toggleSettingsPanelButton.addEventListener("click", toggleSettingsPanel);
    dom.focusPlanningButton.addEventListener("click", enterPlanningFocus);
    dom.exitPlanningFocusButton.addEventListener("click", exitPlanningFocus);
    dom.projectModalClose.addEventListener("click", closeProjectModal);
    dom.projectModalForm.addEventListener("submit", onProjectModalSubmit);
    dom.projectModalForm.addEventListener("click", onProjectModalFormClick);
    dom.projectModalForm.addEventListener("focusout", onProjectModalFormFocusOut);
    dom.projectModalDescriptionToggle.addEventListener("click", toggleProjectModalDescription);
    dom.projectModalDeleteButton.addEventListener("click", onProjectModalDelete);
    dom.projectModalClearButton.addEventListener("click", onProjectModalClear);
    dom.projectModalStartInput.addEventListener("input", syncProjectModalDateBounds);
    dom.projectModalEndInput.addEventListener("input", syncProjectModalDateBounds);
    dom.projectModalTitleInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalRefInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalServiceInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalTypeInput.addEventListener("change", syncProjectModalDisplays);
    dom.projectModalParentInput.addEventListener("change", onProjectModalParentChange);
    dom.projectModalColorInput.addEventListener("input", onProjectModalColorChange);
    dom.projectModalColorHexInput.addEventListener("change", onProjectModalColorHexChange);
    dom.projectModalRiskGainInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalBudgetInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalPrioritizationInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalStatusInput.addEventListener("change", syncProjectModalDisplays);
    dom.projectModalProgressInput.addEventListener("input", syncProjectModalDisplays);
    dom.projectModalDescriptionInput.addEventListener("input", syncProjectModalDisplays);
    if (dom.projectModalCreateInYouTrack) {
        dom.projectModalCreateInYouTrack.addEventListener("change", onProjectModalYouTrackToggleChange);
    }
    if (dom.projectModalTeamMenu) {
        dom.projectModalTeamMenu.addEventListener("change", onProjectModalTeamMenuChange);
        dom.projectModalTeamMenu.addEventListener("input", onProjectModalTeamMenuInput);
    }
    if (dom.projectModalYouTrackColumnsMenu) {
        dom.projectModalYouTrackColumnsMenu.addEventListener("change", onProjectModalYouTrackColumnsMenuChange);
    }
    if (dom.projectModalYouTrackTaskToggle) {
        dom.projectModalYouTrackTaskToggle.addEventListener("click", openProjectModalYouTrackTaskDraft);
    }
    if (dom.projectModalYouTrackTasksBody) {
        dom.projectModalYouTrackTasksBody.addEventListener("click", onProjectModalYouTrackTasksBodyClick);
        dom.projectModalYouTrackTasksBody.addEventListener("input", onProjectModalYouTrackTasksBodyInput);
        dom.projectModalYouTrackTasksBody.addEventListener("change", onProjectModalYouTrackTasksBodyChange);
        dom.projectModalYouTrackTasksBody.addEventListener("focusout", onProjectModalYouTrackTasksBodyFocusOut);
        dom.projectModalYouTrackTasksBody.addEventListener("keydown", onProjectModalYouTrackTasksBodyKeydown);
    }
    dom.projectModal.addEventListener("click", (event) => {
        if (event.target.hasAttribute("data-close-project-modal")) {
            closeProjectModal();
        }
    });

    dom.projectPool.addEventListener("dragstart", onBacklogDragStart);
    dom.projectPool.addEventListener("dragend", onBacklogDragEnd);
    dom.projectPool.addEventListener("click", onProjectPoolClick);
    dom.projectPool.addEventListener("change", onProjectColorChange);
    dom.projectPool.addEventListener("change", onProjectStatusChange);
    dom.projectPool.addEventListener("focusout", onProjectStatusEditorFocusOut);

    dom.timelineRows.addEventListener("dragover", onTimelineDragOver);
    dom.timelineRows.addEventListener("dragleave", onTimelineDragLeave);
    dom.timelineRows.addEventListener("drop", onTimelineDrop);
    dom.timelineRows.addEventListener("dragstart", onTimelineRowReorderDragStart);
    dom.timelineRows.addEventListener("dragend", onTimelineRowReorderDragEnd);
    dom.timelineRows.addEventListener("click", onTimelineClick);
    dom.timelineRows.addEventListener("pointerdown", onTimelinePointerDown);

    document.addEventListener("pointermove", onPointerMove);
    document.addEventListener("pointerup", onPointerUp);
    document.addEventListener("fullscreenchange", onPlanningFullscreenChange);
    document.addEventListener("keydown", (event) => {
        if (!dom.projectModal.hidden && event.key === "Escape") {
            closeProjectModal();
            return;
        }

        if (event.key === "Escape" && rootHasClass("planning-focus-mode")) {
            exitPlanningFocus();
        }
    });
}

function render() {
    syncControls();
    syncPlanningFocusUi();
    renderTimeline();
    renderBacklog();
    renderSummary();
}

function syncControls() {
    dom.timelineStart.value = state.settings.timelineStart;
    dom.visibleMonths.value = String(state.settings.visibleMonths);
    dom.monthWidth.value = String(state.settings.monthWidth);
    dom.serviceFilter.value = state.settings.serviceFilter;
    dom.typeFilter.value = state.settings.typeFilter;
    dom.statusFilter.value = state.settings.statusFilter;
    dom.searchInput.value = state.settings.search;

    dom.visibleMonthsValue.textContent = `${state.settings.visibleMonths} mois`;
    dom.monthWidthValue.textContent = `${state.settings.monthWidth} px`;
    dom.timelineZoomValue.textContent = formatTimelineZoom(getTimelineZoom());
    dom.timelineZoomOutButton.disabled = getTimelineZoom() <= TIMELINE_ZOOM_MIN;
    dom.timelineZoomInButton.disabled = getTimelineZoom() >= TIMELINE_ZOOM_MAX;
    syncPlanningSidebarToggle();
    syncTodayMarkerToggle();
    syncTimelineProgressToggle();
    syncBacklogViewToggle();
    syncSettingsPanelToggle();
    syncServiceColorControls();
}

function renderSummary() {
    const scheduled = state.projects.filter(isScheduled);
    const backlog = state.projects.filter((project) => !isScheduled(project));
    const visibleBacklog = backlog.filter(matchesFilters);
    const visibleScheduledRows = buildVisibleTimelineProjects();

    dom.scheduledCount.textContent = String(scheduled.length);
    dom.backlogCount.textContent = String(backlog.length);
    dom.rangeSummary.textContent = `${state.settings.visibleMonths} mois`;

    if (dom.backlogNote) {
        if (backlog.length - visibleBacklog.length || scheduled.length - visibleScheduledRows.length) {
            dom.backlogNote.textContent = `${visibleBacklog.length} projet(s) backlog visibles, ${visibleScheduledRows.length} ligne(s) visibles sur la timeline.`;
        } else {
            dom.backlogNote.textContent = "Faites glisser une carte sur la timeline pour la planifier.";
        }
    }
}

function renderTimeline() {
    const months = buildVisibleMonths();
    const yearGroups = groupMonthsByYear(months);
    const timelineZoom = getTimelineZoom();
    const scaledMonthWidth = getScaledMonthWidth();
    const visibleTimelineProjects = buildVisibleTimelineProjects();

    dom.timelineBoard.style.setProperty("--months-visible", String(state.settings.visibleMonths));
    dom.timelineBoard.style.setProperty("--timeline-scale", String(timelineZoom));
    dom.timelineBoard.style.setProperty("--month-width", `${scaledMonthWidth}px`);
    dom.timelineBoard.style.setProperty("--lane-width", `${state.settings.visibleMonths * scaledMonthWidth}px`);
    dom.timelineBoard.classList.toggle("is-sidebar-collapsed", !state.settings.showPlanningSidebar);

    dom.timelineYears.innerHTML = yearGroups.map((group) => `
        <div class="year-cell" style="width: ${group.count * scaledMonthWidth}px;">
            ${escapeHtml(String(group.year))}
        </div>
    `).join("");

    dom.timelineMonths.innerHTML = months.map((month) => `
        <div class="month-cell" title="${escapeHtml(month.title)}">
            <span class="month-label">${escapeHtml(month.shortLabel)}</span>
            <span class="month-subtitle">${escapeHtml(String(month.year))}</span>
        </div>
    `).join("");

    const rowsMarkup = [...visibleTimelineProjects.map(renderScheduledRow), renderDropRow()];
    if (!visibleTimelineProjects.length) {
        rowsMarkup.unshift(`<div class="timeline-empty">Aucun projet n'est encore planifié dans cette vue. Déposez un projet sur la ligne d'ajout ci-dessous.</div>`);
    }

    const todayMarkerMarkup = renderTodayMarker();
    dom.timelineRows.innerHTML = `${todayMarkerMarkup}${rowsMarkup.join("")}`;
}

function renderDropRow() {
    return `
        <div class="timeline-row drop-row">
            <div class="row-label timeline-sticky compact-row-label">
                <span class="ref-badge">Ajout</span>
                <h3>Déposez un projet sur le mois voulu</h3>
                <span class="chip">Durée initiale <strong>${escapeHtml(formatDuration(state.settings.defaultDuration))}</strong></span>
            </div>
            <div class="lane" data-dropzone="new">
                <div class="drop-copy">Glissez une carte sur la timeline</div>
            </div>
        </div>
    `;
}

function renderScheduledRow(rowData) {
    const project = rowData.project;
    const depth = Number(rowData.depth || 0);
    const hasChildren = Boolean(rowData.hasChildren);
    const expanded = Boolean(rowData.expanded);
    const bar = getBarMetrics(project);
    const barWidth = Math.max(26, bar.width - 12);
    const barClasses = ["timeline-bar"];
    const rowClasses = ["timeline-row"];
    const progression = normalizeProjectProgression(project.progression);
    const progressMarkup = state.settings.showTimelineProgress === false
        ? ""
        : `<div class="timeline-bar-progress" style="width: ${progression}%;"></div>`;
    if (bar.isOutside) {
        barClasses.push("is-outside");
    }

    const toggleMarkup = hasChildren
        ? `<button class="timeline-bar-toggle" type="button" data-toggle-children="${escapeHtml(project.id)}" aria-expanded="${expanded ? "true" : "false"}" aria-label="${expanded ? "Masquer" : "Afficher"} les sous-projets de ${escapeHtml(project.title)}">${expanded ? "-" : "+"}</button>`
        : "";
    const hasProjectRef = Boolean(String(project.ref || "").trim());
    const hasProjectTitle = Boolean(String(project.title || "").trim());
    const inlineRefMarkup = hasProjectRef
        ? `<span class="bar-ref-inline">${escapeHtml(project.ref)}</span>`
        : "";
    const inlineSeparatorMarkup = hasProjectRef && hasProjectTitle
        ? `<span class="bar-title-separator" aria-hidden="true">|</span>`
        : "";
    const inlineTitleMarkup = hasProjectTitle
        ? `<span class="bar-title-text">${escapeHtml(project.title)}</span>`
        : "";
    const rowTitle = [project.ref, project.title]
        .map((value) => String(value || "").trim())
        .filter(Boolean)
        .join(" | ");
    const removeButtonTitle = project.parentProjectId
        ? "Supprimer la liaison avec le projet parent"
        : "Retirer le projet";
    const projectTypeMarkup = project.projectType
        ? `<span class="chip project-type-chip">${escapeHtml(project.projectType)}</span>`
        : "";
    const reorderHandleMarkup = `
        <button
            class="timeline-row-reorder-handle"
            type="button"
            draggable="true"
            data-row-reorder-handle="${escapeHtml(project.id)}"
            aria-label="Changer l'ordre du projet ${escapeHtml(project.title || project.ref || project.id)}"
            title="Changer l'ordre dans la timeline"
        >
            <i class="bi bi-grip-vertical" aria-hidden="true"></i>
        </button>`;

    return `
        <div class="${rowClasses.join(" ")}" data-project-id="${escapeHtml(project.id)}" style="--tree-depth:${depth};">
            <div class="row-label timeline-sticky compact-row-label project-tree-label">
                ${reorderHandleMarkup}
                <span class="timeline-tree-indent" aria-hidden="true"></span>
                <h3>${escapeHtml(rowTitle || project.title || project.ref || "Projet sans nom")}</h3>
                <div class="service-line compact-inline">
                    ${tokenizeService(project.service).map((token) => `<span class="chip">${escapeHtml(token)}</span>`).join("")}
                    ${projectTypeMarkup}
                </div>
            </div>
            <div class="lane" data-project-lane="${escapeHtml(project.id)}">
                <div
                    class="${barClasses.join(" ")}"
                    data-bar-id="${escapeHtml(project.id)}"
                    style="left: ${bar.left + 6}px; width: ${barWidth}px; --bar-color: ${escapeHtml(project.color)};"
                >
                    ${progressMarkup}
                    <span class="resize-handle" data-resize="left" data-project-id="${escapeHtml(project.id)}"></span>
                    <div class="bar-main">
                        <div class="bar-title-line">
                            ${toggleMarkup}
                            <strong>
                                ${inlineRefMarkup}
                                ${inlineSeparatorMarkup}
                                ${inlineTitleMarkup}
                            </strong>
                        </div>
                    </div>
                    <button class="bar-remove" type="button" title="${escapeHtml(removeButtonTitle)}" aria-label="${escapeHtml(removeButtonTitle)}" data-unschedule="${escapeHtml(project.id)}">×</button>
                    <span class="resize-handle" data-resize="right" data-project-id="${escapeHtml(project.id)}"></span>
                </div>
            </div>
        </div>
    `;
}

function renderTodayMarker() {
    if (state.settings.showTodayMarker === false) {
        return "";
    }

    const offset = getTodayMarkerOffset();
    if (offset === null) {
        return "";
    }

    return `
        <div class="timeline-today-marker" style="left: calc(var(--sidebar-width) + ${offset}px);" aria-hidden="true">
            <span class="timeline-today-line"></span>
        </div>
    `;
}

function renderBacklog() {
    const backlogProjects = state.projects
        .filter(matchesFilters)
        .sort((left, right) => {
            const scheduledDifference = Number(isScheduled(left)) - Number(isScheduled(right));
            if (scheduledDifference !== 0) {
                return scheduledDifference;
            }

            return left.ref.localeCompare(right.ref, "fr");
        });
    const isTableView = getBacklogView() === "table";

    dom.projectPool.classList.toggle("is-table-view", isTableView);

    if (!backlogProjects.length) {
        dom.projectPool.innerHTML = `<div class="pool-empty">Aucun projet ne correspond au filtre actuel.</div>`;
        return;
    }

    dom.projectPool.innerHTML = isTableView
        ? renderBacklogTable(backlogProjects)
        : renderBacklogCards(backlogProjects);
}

function renderProjectMetaSummary(project) {
    const summaryParts = [];
    const projectType = normalizeProjectType(project.projectType);
    const parentProject = getProjectParent(project);

    if (projectType) {
        summaryParts.push(projectType);
    }

    if (parentProject) {
        summaryParts.push(`Parent: ${parentProject.ref}`);
    }

    if (!summaryParts.length) {
        return "";
    }

    return `<small class="project-meta-summary">${escapeHtml(summaryParts.join(" | "))}</small>`;
}

function renderBacklogCards(backlogProjects) {
    return backlogProjects.map((project) => {
        const scheduled = isScheduled(project);

        return `
        <article
            class="project-card${scheduled ? " is-scheduled" : ""}"
            draggable="${scheduled ? "false" : "true"}"
            data-project-card="${escapeHtml(project.id)}"
            data-project-scheduled="${scheduled ? "true" : "false"}"
        >
            <div class="card-topline">
                <span class="ref-badge">${escapeHtml(project.ref)}</span>
                <input
                    class="card-color"
                    type="color"
                    value="${escapeHtml(project.color)}"
                    data-project-color="${escapeHtml(project.id)}"
                    aria-label="Choisir la couleur du projet ${escapeHtml(project.ref)}"
                    title="Choisir la couleur"
                    draggable="false"
                >
            </div>
            <h3>${escapeHtml(project.title)}</h3>
            <p>${escapeHtml(project.service)}</p>
            ${renderProjectMetaSummary(project)}
            ${renderProjectStatusControl(project)}
        </article>
    `;
    }).join("");
}

function renderBacklogTable(backlogProjects) {
    return `
        <div class="project-table-wrap">
            <table class="project-table">
                <colgroup>
                    <col class="project-table-col-ref">
                    <col class="project-table-col-project">
                    <col class="project-table-col-service">
                    <col class="project-table-col-color">
                    <col class="project-table-col-status">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Réf.</th>
                        <th scope="col">Projet</th>
                        <th scope="col">Service</th>
                        <th scope="col">Couleur</th>
                        <th scope="col">État</th>
                    </tr>
                </thead>
                <tbody>
                    ${backlogProjects.map((project) => renderBacklogTableRow(project)).join("")}
                </tbody>
            </table>
        </div>
    `;
}

function renderBacklogTableRow(project) {
    const scheduled = isScheduled(project);

    return `
        <tr
            class="project-table-row${scheduled ? " is-scheduled" : ""}"
            draggable="${scheduled ? "false" : "true"}"
            data-project-card="${escapeHtml(project.id)}"
            data-project-scheduled="${scheduled ? "true" : "false"}"
            style="--project-row-color:${escapeHtml(project.color)};"
        >
            <td data-label="Réf.">
                <div class="project-table-ref">
                    <span class="ref-badge">${escapeHtml(project.ref)}</span>
                </div>
            </td>
            <td data-label="Projet">
                <strong class="project-table-title">${escapeHtml(project.title)}</strong>
                ${renderProjectMetaSummary(project)}
            </td>
            <td data-label="Service">
                <span class="project-table-service">${escapeHtml(project.service)}</span>
            </td>
            <td class="project-table-color-cell" data-label="Couleur">
                <span class="project-table-color">
                    <input
                        class="card-color"
                        type="color"
                        value="${escapeHtml(project.color)}"
                        data-project-color="${escapeHtml(project.id)}"
                        aria-label="Choisir la couleur du projet ${escapeHtml(project.ref)}"
                        title="Choisir la couleur"
                        draggable="false"
                    >
                </span>
            </td>
            <td class="project-table-status-cell" data-label="État">
                ${renderProjectStatusControl(project)}
            </td>
        </tr>
    `;
}

function renderProjectStatusControl(project) {
    const normalizedStatus = normalizeProjectStatus(project.status, project);

    return `
        <div class="project-status-row" data-project-status-row="${escapeHtml(project.id)}">
            <button
                class="project-status-badge ${escapeHtml(getProjectStatusMeta(project.status, project).className)}"
                type="button"
                data-project-status-button="${escapeHtml(project.id)}"
                draggable="false"
            >
                ${escapeHtml(normalizedStatus)}
            </button>
            <select class="project-status-select" data-project-status-select="${escapeHtml(project.id)}" draggable="false" aria-label="Changer l'état du projet ${escapeHtml(project.ref)}">
                ${renderProjectStatusOptions(normalizedStatus)}
            </select>
        </div>
    `;
}

function populateServiceFilter() {
    const selectedValue = state.settings.serviceFilter || "all";
    const services = [...new Set(
        state.projects.flatMap((project) => tokenizeService(project.service))
    )].sort((left, right) => left.localeCompare(right, "fr"));

    dom.serviceFilter.innerHTML = [
        `<option value="all">Tous les services</option>`,
        ...services.map((service) => `<option value="${escapeHtml(service)}">${escapeHtml(service)}</option>`)
    ].join("");

    dom.serviceFilter.value = services.includes(selectedValue) || selectedValue === "all"
        ? selectedValue
        : "all";
    state.settings.serviceFilter = dom.serviceFilter.value;
    populateTypeFilter();
}

function populateTypeFilter() {
    const selectedValue = state.settings.typeFilter || "all";
    const typedProjects = state.projects
        .map((project) => normalizeProjectType(project.projectType))
        .filter(Boolean);
    const hasEmptyType = state.projects.some((project) => !normalizeProjectType(project.projectType));
    const types = [...new Set(typedProjects)].sort((left, right) => left.localeCompare(right, "fr"));

    dom.typeFilter.innerHTML = [
        `<option value="all">Tous les types</option>`,
        ...types.map((type) => `<option value="${escapeHtml(type)}">${escapeHtml(type)}</option>`),
        ...(hasEmptyType ? [`<option value="${EMPTY_PROJECT_TYPE_FILTER}">Non renseigné</option>`] : [])
    ].join("");

    const isKnownType = types.includes(selectedValue) || selectedValue === "all" || (hasEmptyType && selectedValue === EMPTY_PROJECT_TYPE_FILTER);
    dom.typeFilter.value = isKnownType ? selectedValue : "all";
    state.settings.typeFilter = dom.typeFilter.value;
    populateStatusFilter();
}

function populateStatusFilter() {
    const selectedValue = state.settings.statusFilter || "all";
    dom.statusFilter.innerHTML = [
        `<option value="all">Tous les états</option>`,
        ...PROJECT_STATUSES.map((status) => `<option value="${escapeHtml(status.value)}">${escapeHtml(status.value)}</option>`)
    ].join("");

    const statusValues = PROJECT_STATUSES.map((status) => status.value);
    dom.statusFilter.value = statusValues.includes(selectedValue) ? selectedValue : "all";
    state.settings.statusFilter = dom.statusFilter.value;
}

function onBacklogDragStart(event) {
    const card = event.target.closest("[data-project-card]");
    if (!card || card.dataset.projectScheduled === "true") {
        return;
    }

    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", card.dataset.projectCard);
    card.classList.add("is-dragging");
}

function onBacklogDragEnd(event) {
    const card = event.target.closest("[data-project-card]");
    if (card) {
        card.classList.remove("is-dragging");
    }

    clearDropTargets();
}

function onProjectPoolClick(event) {
    if (event.target.closest("[data-project-color]")) {
        return;
    }

    const statusButton = event.target.closest("[data-project-status-button]");
    if (statusButton) {
        openProjectStatusEditor(statusButton.dataset.projectStatusButton);
        return;
    }

    if (event.target.closest("[data-project-status-select]")) {
        return;
    }

    const card = event.target.closest("[data-project-card]");
    if (!card) {
        return;
    }

    const project = findProject(card.dataset.projectCard);
    if (!project) {
        return;
    }

    openProjectModal(project);
}

function openProjectStatusEditor(projectId) {
    const row = dom.projectPool.querySelector(`[data-project-status-row="${projectId}"]`);
    if (!row) {
        return;
    }

    row.classList.add("is-editing");
    const select = row.querySelector("[data-project-status-select]");
    if (select instanceof HTMLElement) {
        requestAnimationFrame(() => select.focus());
    }
}

function onProjectStatusChange(event) {
    const select = event.target.closest("[data-project-status-select]");
    if (!select) {
        return;
    }

    const project = findProject(select.dataset.projectStatusSelect);
    if (!project) {
        return;
    }

    project.status = normalizeProjectStoredStatus(select.value, project);
    persistState();
    render();
}

function onProjectStatusEditorFocusOut(event) {
    const row = event.target.closest("[data-project-status-row]");
    if (!row) {
        return;
    }

    const nextTarget = event.relatedTarget;
    if (nextTarget instanceof HTMLElement && row.contains(nextTarget)) {
        return;
    }

    row.classList.remove("is-editing");
}

function onTimelineDragOver(event) {
    const rowReorderProjectId = timelineRowReorder?.projectId
        || event.dataTransfer?.getData(TIMELINE_ROW_REORDER_MIME)
        || "";
    if (rowReorderProjectId) {
        const rowReorderDropTarget = getTimelineRowReorderDropTarget(event, rowReorderProjectId);
        const parentDropTarget = getTimelineParentDropTargetFromElement(event.target, rowReorderProjectId);
        if (!rowReorderDropTarget && !parentDropTarget) {
            return;
        }

        event.preventDefault();
        clearDropTargets();

        if (rowReorderDropTarget) {
            rowReorderDropTarget.row.classList.add(
                rowReorderDropTarget.position === "before"
                    ? "is-row-reorder-target-before"
                    : "is-row-reorder-target-after"
            );
            return;
        }

        if (parentDropTarget?.row) {
            parentDropTarget.row.classList.add("is-child-drop-target");
        }
        return;
    }

    const draggedProjectId = event.dataTransfer?.getData("text/plain") || "";
    const parentDropTarget = getTimelineParentDropTargetFromElement(event.target, draggedProjectId);
    const lane = event.target.closest(".lane");

    if (!parentDropTarget && !lane) {
        return;
    }

    event.preventDefault();
    clearDropTargets();

    if (parentDropTarget?.row) {
        parentDropTarget.row.classList.add("is-child-drop-target");
        return;
    }

    const row = lane?.closest(".timeline-row");
    if (row) {
        row.classList.add("is-drop-target");
    }
}

function onTimelineDragLeave(event) {
    const currentRow = event.target.closest(".timeline-row");
    const nextRow = event.relatedTarget?.closest?.(".timeline-row");
    if (currentRow && currentRow !== nextRow) {
        currentRow.classList.remove("is-drop-target");
        currentRow.classList.remove("is-child-drop-target");
        currentRow.classList.remove("is-row-reorder-target-before");
        currentRow.classList.remove("is-row-reorder-target-after");
    }
}

function onTimelineDrop(event) {
    const rowReorderProjectId = timelineRowReorder?.projectId
        || event.dataTransfer?.getData(TIMELINE_ROW_REORDER_MIME)
        || "";
    if (rowReorderProjectId) {
        const rowReorderDropTarget = getTimelineRowReorderDropTarget(event, rowReorderProjectId);
        const parentDropTarget = getTimelineParentDropTargetFromElement(event.target, rowReorderProjectId);
        if (!rowReorderDropTarget && !parentDropTarget) {
            return;
        }

        event.preventDefault();
        clearDropTargets();

        const project = findProject(rowReorderProjectId);
        if (!project) {
            return;
        }

        if (rowReorderDropTarget) {
            const targetProject = findProject(rowReorderDropTarget.projectId);
            if (!targetProject) {
                return;
            }

            if (moveTimelineProjectRelativeTo(project, targetProject, rowReorderDropTarget.position)) {
                persistState();
                render();
            }
            return;
        }

        if (parentDropTarget) {
            const parentProject = findProject(parentDropTarget.projectId);
            if (parentProject && assignProjectToParent(project, parentProject, { expandParent: true })) {
                persistState();
                render();
            }
        }
        return;
    }

    const lane = event.target.closest(".lane");
    const projectId = event.dataTransfer.getData("text/plain");
    const project = findProject(projectId);
    const parentDropTarget = getTimelineParentDropTargetFromElement(event.target, projectId);

    if (!lane && !parentDropTarget) {
        return;
    }

    event.preventDefault();
    clearDropTargets();

    if (!project) {
        return;
    }

    if (parentDropTarget) {
        const parentProject = findProject(parentDropTarget.projectId);
        if (!parentProject) {
            return;
        }

        if (!assignProjectToParent(project, parentProject, { expandParent: true })) {
            return;
        }

        persistState();
        render();
        return;
    }

    const rect = lane.getBoundingClientRect();
    const slotIndex = clamp(
        Math.floor((event.clientX - rect.left) / getHalfMonthWidth()),
        0,
        getTotalVisibleSlots() - 1
    );

    scheduleProject(project, slotIndex, { clearParent: true });
}

function onTimelineClick(event) {
    if (event.target.closest("[data-row-reorder-handle]")) {
        return;
    }

    const treeToggle = event.target.closest("[data-toggle-children]");
    if (treeToggle) {
        toggleTimelineProjectExpanded(treeToggle.dataset.toggleChildren);
        return;
    }

    const unscheduleButton = event.target.closest("[data-unschedule]");
    if (!unscheduleButton) {
        const row = event.target.closest(".timeline-row[data-project-id]");
        if (!row || event.target.closest("[data-bar-id]")) {
            return;
        }

        const rowProject = findProject(row.dataset.projectId);
        if (rowProject) {
            openProjectModal(rowProject);
        }
        return;
    }

    const project = findProject(unscheduleButton.dataset.unschedule);
    if (!project) {
        return;
    }

    if (detachProjectFromParent(project)) {
        persistState();
        render();
        return;
    }

    clearProjectSchedule(project);
    persistState();
    render();
}

function onProjectColorChange(event) {
    const colorInput = event.target.closest("[data-project-color]");
    if (!colorInput) {
        return;
    }

    const project = findProject(colorInput.dataset.projectColor);
    if (!project) {
        return;
    }

    setProjectColorFromValue(project, colorInput.value);
    persistState();
    render();
}

function onTimelinePointerDown(event) {
    if (event.button !== 0) {
        return;
    }

    if (event.target.closest("[data-toggle-children]")) {
        return;
    }

    if (event.target.closest("[data-unschedule]")) {
        return;
    }

    const resizeHandle = event.target.closest("[data-resize]");
    const bar = event.target.closest("[data-bar-id]");
    if (!bar) {
        return;
    }

    const project = findProject(bar.dataset.barId);
    if (!project) {
        return;
    }

    interaction = {
        type: resizeHandle ? `resize-${resizeHandle.dataset.resize}` : "move",
        projectId: project.id,
        originX: event.clientX,
        initialStartIndex: getTimelineSlotIndex(project.start),
        initialDuration: project.duration,
        previewStartIndex: getTimelineSlotIndex(project.start),
        previewDuration: project.duration,
        barElement: bar,
        moved: false
    };

    addRootClass("is-scrubbing");
    event.preventDefault();
}

function onPointerMove(event) {
    if (!interaction) {
        return;
    }

    if (Math.abs(event.clientX - interaction.originX) > 4) {
        interaction.moved = true;
    }

    const slotDelta = Math.round((event.clientX - interaction.originX) / getHalfMonthWidth());
    let nextStartIndex = interaction.initialStartIndex;
    let nextDuration = interaction.initialDuration;

    if (interaction.type === "move") {
        const maxStart = Math.max(0, getTotalVisibleSlots() - interaction.initialDuration);
        nextStartIndex = clamp(interaction.initialStartIndex + slotDelta, 0, maxStart);
    } else if (interaction.type === "resize-left") {
        const maxStart = interaction.initialStartIndex + interaction.initialDuration - 1;
        nextStartIndex = clamp(interaction.initialStartIndex + slotDelta, 0, maxStart);
        nextDuration = interaction.initialDuration + (interaction.initialStartIndex - nextStartIndex);
    } else if (interaction.type === "resize-right") {
        const maxDuration = Math.max(1, getTotalVisibleSlots() - interaction.initialStartIndex);
        nextDuration = clamp(interaction.initialDuration + slotDelta, 1, maxDuration);
    }

    interaction.previewStartIndex = nextStartIndex;
    interaction.previewDuration = nextDuration;
    previewBar(interaction.barElement, nextStartIndex, nextDuration);

    if (interaction.type === "move") {
        syncPointerTimelineDropTarget(event.clientX, event.clientY, interaction.projectId);
    } else {
        clearDropTargets();
    }
}

function onPointerUp(event) {
    if (!interaction) {
        return;
    }

    const project = findProject(interaction.projectId);
    if (project) {
        const shouldOpenModal = interaction.type === "move" && !interaction.moved;
        const rowReorderDropTarget = interaction.type === "move" && interaction.moved
            ? getTimelineRowReorderDropTargetFromPoint(event.clientX, event.clientY, interaction.projectId)
            : null;
        const parentDropTarget = interaction.type === "move" && interaction.moved
            ? getTimelineParentDropTargetFromPoint(event.clientX, event.clientY, interaction.projectId)
            : null;

        if (shouldOpenModal) {
            openProjectModal(project);
        } else if (rowReorderDropTarget) {
            const targetProject = findProject(rowReorderDropTarget.projectId);
            if (targetProject && moveTimelineProjectRelativeTo(project, targetProject, rowReorderDropTarget.position)) {
                persistState();
                render();
            } else {
                render();
            }
        } else if (parentDropTarget) {
            const parentProject = findProject(parentDropTarget.projectId);
            if (parentProject && assignProjectToParent(project, parentProject, { expandParent: true })) {
                persistState();
                render();
            } else {
                render();
            }
        } else {
            project.start = slotIndexToStartDate(state.settings.timelineStart, interaction.previewStartIndex);
            project.duration = interaction.previewDuration;
            syncProjectExactDatesWithSchedule(project);
            syncProjectScheduleHierarchy(project);
            persistState();
            render();
        }
    }

    interaction = null;
    clearDropTargets();
    removeRootClass("is-scrubbing");
}

function onTimelineRowReorderDragStart(event) {
    const handle = event.target.closest("[data-row-reorder-handle]");
    if (!handle) {
        return;
    }

    const projectId = String(handle.dataset.rowReorderHandle || "").trim();
    const project = findProject(projectId);
    if (!project || !isScheduled(project)) {
        event.preventDefault();
        return;
    }

    timelineRowReorder = { projectId };
    event.dataTransfer?.setData(TIMELINE_ROW_REORDER_MIME, projectId);
    event.dataTransfer?.setData("text/plain", projectId);
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
    }

    handle.closest(".timeline-row")?.classList.add("is-row-reordering");
    addRootClass("is-row-reordering");
}

function onTimelineRowReorderDragEnd(event) {
    if (!timelineRowReorder) {
        return;
    }

    event.target.closest(".timeline-row")?.classList.remove("is-row-reordering");
    dom.timelineRows
        .querySelectorAll(".timeline-row.is-row-reordering")
        .forEach((row) => row.classList.remove("is-row-reordering"));
    timelineRowReorder = null;
    clearDropTargets();
    removeRootClass("is-row-reordering");
}

function scheduleProject(project, slotIndex, options = {}) {
    if (normalizeProjectStoredStatus(project.status, project) === "A planifier") {
        project.status = "Planifié";
    }

    if (options.clearParent) {
        project.parentProjectId = null;
    }

    project.start = slotIndexToStartDate(state.settings.timelineStart, slotIndex);
    project.duration = 2;
    project.lane = getNextLane();
    syncProjectExactDatesWithSchedule(project);
    syncProjectScheduleHierarchy(project);
    persistState();
    render();
}

function previewBar(element, startIndex, duration) {
    element.style.left = `${startIndex * getHalfMonthWidth() + 6}px`;
    element.style.width = `${Math.max(26, duration * getHalfMonthWidth() - 12)}px`;
}

async function exportPlanning() {
    const originalLabel = dom.exportButton.textContent;
    dom.exportButton.disabled = true;
    dom.exportButton.textContent = "Export en cours...";

    try {
        const payload = await apiRequest(API_ROUTES.exportProjects, {
            method: "POST",
            body: JSON.stringify({
                projects: state.projects.map((project) => {
                    const editableDates = getProjectEditableDates(project);
                    return {
                        id: project.id,
                        ref: project.ref,
                        title: project.title,
                        service: project.service,
                        color: project.color,
                        start: project.start,
                        duration: project.duration,
                        lane: project.lane,
                        startExact: editableDates.start || null,
                        endExact: editableDates.end || null
                    };
                })
            })
        });

        triggerExportDownload(payload.downloadUrl, payload.fileName);
        window.alert(`Export Excel enregistré dans /export/${payload.fileName}\nLe téléchargement du fichier a aussi été lancé.`);
    } catch (error) {
        console.error(error);
        window.alert(error.message || "L'export Excel a échoué.");
    } finally {
        dom.exportButton.disabled = false;
        dom.exportButton.textContent = originalLabel;
    }
}

function triggerExportDownload(downloadUrl, fileName) {
    if (!downloadUrl) {
        return;
    }

    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = fileName || "";
    link.rel = "noopener";
    document.body.appendChild(link);
    link.click();
    link.remove();
}

async function enterPlanningFocus() {
    addRootClass("planning-focus-mode");
    setPlanningLayoutHidden(true);
    syncPlanningFocusUi();
    await requestPlanningFullscreen();
}

async function exitPlanningFocus() {
    if (document.fullscreenElement) {
        try {
            await document.exitFullscreen();
        } catch (error) {
            console.warn("Impossible de quitter le plein écran proprement.", error);
        }
    }

    removeRootClass("planning-focus-mode");
    setPlanningLayoutHidden(false);
    syncPlanningFocusUi();
}

function syncPlanningFocusUi() {
    const isFocusMode = rootHasClass("planning-focus-mode");

    if (dom.focusPlanningButton) {
        dom.focusPlanningButton.hidden = isFocusMode;
    }

    if (dom.exitPlanningFocusButton) {
        dom.exitPlanningFocusButton.hidden = !isFocusMode;
        dom.exitPlanningFocusButton.textContent = "Revenir à la vue initiale";
        dom.exitPlanningFocusButton.title = "Revenir à la vue initiale";
    }
}

function togglePlanningSidebar() {
    state.settings.showPlanningSidebar = !state.settings.showPlanningSidebar;
    persistState();
    render();
}

function toggleTodayMarker() {
    state.settings.showTodayMarker = state.settings.showTodayMarker === false;
    persistState();
    render();
}

function toggleTimelineProgress() {
    state.settings.showTimelineProgress = state.settings.showTimelineProgress === false;
    persistState();
    render();
}

function toggleBacklogView() {
    state.settings.backlogView = getBacklogView() === "table" ? "cards" : "table";
    persistState();
    render();
}

function toggleSettingsPanel() {
    state.settings.showSettingsPanel = state.settings.showSettingsPanel === false;
    persistState();
    render();
}

function adjustTimelineZoom(delta) {
    const currentZoom = getTimelineZoom();
    const nextZoom = clamp(roundTimelineZoom(currentZoom + delta), TIMELINE_ZOOM_MIN, TIMELINE_ZOOM_MAX);
    if (nextZoom === currentZoom) {
        return;
    }

    state.settings.timelineZoom = nextZoom;
    persistState();
    render();
}

function syncPlanningSidebarToggle() {
    const isVisible = state.settings.showPlanningSidebar !== false;
    dom.togglePlanningSidebarButton.innerHTML = isVisible
        ? `<i class="bi bi-chevron-bar-left" aria-hidden="true"></i>`
        : `<i class="bi bi-chevron-bar-right" aria-hidden="true"></i>`;
    dom.togglePlanningSidebarButton.setAttribute(
        "aria-label",
        isVisible ? "Masquer la colonne projet" : "Afficher la colonne projet"
    );
    dom.togglePlanningSidebarButton.title = isVisible ? "Masquer la colonne projet" : "Afficher la colonne projet";
}

function syncTodayMarkerToggle() {
    const isVisible = state.settings.showTodayMarker !== false;
    dom.toggleTodayMarkerButton.classList.toggle("is-active", isVisible);
    dom.toggleTodayMarkerButton.setAttribute("aria-pressed", String(isVisible));
    dom.toggleTodayMarkerButton.setAttribute(
        "aria-label",
        isVisible ? "Masquer le repère du jour" : "Afficher le repère du jour"
    );
    dom.toggleTodayMarkerButton.title = isVisible ? "Masquer le repère du jour" : "Afficher le repère du jour";
}

function syncTimelineProgressToggle() {
    const isVisible = state.settings.showTimelineProgress !== false;
    dom.toggleTimelineProgressButton.classList.toggle("is-active", isVisible);
    dom.toggleTimelineProgressButton.setAttribute("aria-pressed", String(isVisible));
    dom.toggleTimelineProgressButton.setAttribute(
        "aria-label",
        isVisible ? "Masquer l'avancement sur la timeline" : "Afficher l'avancement sur la timeline"
    );
    dom.toggleTimelineProgressButton.title = isVisible
        ? "Masquer l'avancement sur la timeline"
        : "Afficher l'avancement sur la timeline";
}

function syncBacklogViewToggle() {
    if (!dom.toggleBacklogViewButton) {
        return;
    }

    const isTableView = getBacklogView() === "table";

    dom.toggleBacklogViewButton.classList.toggle("is-active", isTableView);
    dom.toggleBacklogViewButton.setAttribute("aria-pressed", String(isTableView));
    dom.toggleBacklogViewButton.setAttribute(
        "aria-label",
        isTableView ? "Afficher les projets en cartes" : "Afficher les projets en tableau"
    );
    dom.toggleBacklogViewButton.title = isTableView
        ? "Afficher les projets en cartes"
        : "Afficher les projets en tableau";
    dom.toggleBacklogViewButton.innerHTML = isTableView
        ? `<i class="bi bi-grid-3x2-gap" aria-hidden="true"></i>`
        : `<i class="bi bi-table" aria-hidden="true"></i>`;
}

function syncSettingsPanelToggle() {
    const isVisible = state.settings.showSettingsPanel !== false;

    if (dom.controlsPanel) {
        dom.controlsPanel.hidden = !isVisible;
    }

    dom.toggleSettingsPanelButton.classList.toggle("is-active", isVisible);
    dom.toggleSettingsPanelButton.setAttribute("aria-pressed", String(isVisible));
    dom.toggleSettingsPanelButton.setAttribute(
        "aria-label",
        isVisible ? "Masquer les réglages" : "Afficher les réglages"
    );
    dom.toggleSettingsPanelButton.title = isVisible ? "Masquer les réglages" : "Afficher les réglages";
}

function openProjectModal(project) {
    setProjectModalMode("edit");
    const editableDates = getProjectEditableDates(project);
    dom.projectModalTitle.textContent = project.title;
    dom.projectModalTitleInput.value = project.title || "";
    dom.projectModalRefInput.value = project.ref || "";
    dom.projectModalServiceInput.value = project.service || "";
    populateProjectModalParentOptions(project.id, project.parentProjectId || "");
    dom.projectModalTypeInput.innerHTML = renderProjectTypeOptions(project.projectType);
    dom.projectModalStartInput.value = editableDates.start || "";
    dom.projectModalEndInput.value = editableDates.end || "";
    dom.projectModalColorInput.value = project.color || getDefaultColor(project.service);
    dom.projectModalColorHexInput.value = project.customColor || "";
    dom.projectModalRiskGainInput.value = project.riskGain || "";
    dom.projectModalBudgetInput.value = project.budgetEstimate || "";
    dom.projectModalPrioritizationInput.value = project.prioritization || "";
    dom.projectModalStatusInput.value = normalizeProjectStoredStatus(project.status, project);
    dom.projectModalProgressInput.value = String(normalizeProjectProgression(project.progression));
    dom.projectModalClearButton.hidden = !isScheduled(project);
    dom.projectModal.dataset.projectId = project.id;
    if (getYouTrackProjectKey(project)) {
        dom.projectModal.dataset.youtrackProjectKey = getYouTrackProjectKey(project);
    } else {
        delete dom.projectModal.dataset.youtrackProjectKey;
    }
    if (dom.projectModalCreateInYouTrack) {
        dom.projectModalCreateInYouTrack.checked = isYouTrackProject(project);
    }
    setProjectModalTeamMembers(getProjectTeamMembers(project));
    setProjectModalTaskColumns(getProjectTaskColumns(project));
    hideProjectModalError();
    syncProjectModalDateBounds();
    dom.projectModalDescriptionInput.value = project.description || "";
    syncProjectModalDisplays();
    syncProjectModalYouTrackControls(project);
    syncProjectModalYouTrackBadge(project);
    syncProjectModalYouTrackTeam(project);
    syncProjectModalYouTrackTasks(project);
    setProjectModalDescriptionExpanded(false);
    closeProjectModalEditors();
    dom.projectModal.hidden = false;
    addRootClass("modal-open");
    setProjectModalScrollLock(true);
}

function openCreateProjectModal() {
    const project = createEmptyProjectDraft();

    setProjectModalMode("create");
    dom.projectModalTitle.textContent = "Nouveau projet";
    dom.projectModalTitleInput.value = "";
    dom.projectModalRefInput.value = "";
    dom.projectModalServiceInput.value = "";
    populateProjectModalParentOptions("", "");
    dom.projectModalTypeInput.innerHTML = renderProjectTypeOptions("");
    dom.projectModalStartInput.value = "";
    dom.projectModalEndInput.value = "";
    dom.projectModalColorInput.value = project.color || getDefaultColor(project.service);
    dom.projectModalColorHexInput.value = "";
    dom.projectModalRiskGainInput.value = "";
    dom.projectModalBudgetInput.value = "";
    dom.projectModalPrioritizationInput.value = "";
    dom.projectModalStatusInput.value = "A planifier";
    dom.projectModalProgressInput.value = "0";
    dom.projectModalDescriptionInput.value = "";
    dom.projectModalClearButton.hidden = true;
    delete dom.projectModal.dataset.projectId;
    delete dom.projectModal.dataset.youtrackProjectKey;
    if (dom.projectModalCreateInYouTrack) {
        dom.projectModalCreateInYouTrack.checked = false;
    }
    projectModalTeamState.filterQuery = "";
    setProjectModalTeamMembers([]);
    setProjectModalTaskColumns([...DEFAULT_PROJECT_TASK_COLUMNS]);
    hideProjectModalError();
    syncProjectModalYouTrackControls(null);
    syncProjectModalYouTrackBadge(null);
    resetProjectModalYouTrackTasks();
    syncProjectModalDateBounds();
    syncProjectModalDisplays();
    setProjectModalDescriptionExpanded(false);
    closeProjectModalEditors();
    dom.projectModal.hidden = false;
    addRootClass("modal-open");
    setProjectModalScrollLock(true);

    requestAnimationFrame(() => {
        openProjectModalEditor(dom.projectModalForm.querySelector(".project-modal-title-edit"));
    });
}

function closeProjectModal() {
    projectModalTasksRequestToken += 1;
    projectModalTeamRequestToken += 1;
    dom.projectModal.hidden = true;
    delete dom.projectModal.dataset.projectId;
    delete dom.projectModal.dataset.youtrackProjectKey;
    delete dom.projectModal.dataset.mode;
    dom.projectModalForm.reset();
    if (dom.projectModalCreateInYouTrack) {
        dom.projectModalCreateInYouTrack.checked = false;
        dom.projectModalCreateInYouTrack.disabled = false;
    }
    projectModalTeamState.members = [];
    projectModalTeamState.canManage = false;
    projectModalTeamState.filterQuery = "";
    projectModalTaskColumnsState.columns = [...DEFAULT_PROJECT_TASK_COLUMNS];
    setProjectModalMode("edit");
    setProjectModalDescriptionExpanded(false);
    closeProjectModalEditors();
    hideProjectModalError();
    syncProjectModalYouTrackBadge(null);
    resetProjectModalYouTrackTasks();
    removeRootClass("modal-open");
    setProjectModalScrollLock(false);
}

function getProjectModalMode() {
    return dom.projectModal.dataset.mode === "create" ? "create" : "edit";
}

function setProjectModalMode(mode) {
    const isCreateMode = mode === "create";

    dom.projectModal.dataset.mode = isCreateMode ? "create" : "edit";

    if (dom.projectModalKicker) {
        dom.projectModalKicker.textContent = isCreateMode ? "Nouveau projet" : "Détail projet";
    }

    if (dom.projectModalSubmitButton) {
        dom.projectModalSubmitButton.textContent = isCreateMode ? "Créer le projet" : "Valider";
        dom.projectModalSubmitButton.disabled = false;
    }

    if (dom.projectModalDeleteButton) {
        dom.projectModalDeleteButton.hidden = isCreateMode;
        dom.projectModalDeleteButton.disabled = false;
    }

    if (isCreateMode) {
        syncProjectModalYouTrackBadge(null);
        resetProjectModalYouTrackTasks();
    }
}

function toggleProjectModalDescription() {
    const isExpanded = dom.projectModalDescriptionToggle.getAttribute("aria-expanded") === "true";
    setProjectModalDescriptionExpanded(!isExpanded);
}

function setProjectModalDescriptionExpanded(expanded) {
    if (!dom.projectModalDescriptionBlock || !dom.projectModalDescriptionToggle) {
        return;
    }

    dom.projectModalDescriptionBlock.hidden = !expanded;
    dom.projectModalDescriptionToggle.setAttribute("aria-expanded", expanded ? "true" : "false");

    if (dom.projectModalDescriptionToggleIcon) {
        dom.projectModalDescriptionToggleIcon.textContent = expanded ? "-" : "+";
    }

    if (!expanded) {
        dom.projectModalDescriptionBlock.classList.remove("is-editing");
    }
}

function setProjectModalScrollLock(locked) {
    const method = locked ? "add" : "remove";
    document.documentElement.classList[method]("project-modal-scroll-locked");
    document.body.classList[method]("project-modal-scroll-locked");
}

function getProjectModalCurrentProject() {
    const projectId = dom.projectModal.dataset.projectId || "";
    return projectId ? findProject(projectId) : null;
}

function isProjectModalYouTrackEnabled(project = getProjectModalCurrentProject()) {
    if (dom.projectModalCreateInYouTrack) {
        return Boolean(dom.projectModalCreateInYouTrack.checked);
    }

    return isYouTrackProject(project);
}

function isProjectModalYouTrackRequested(project = getProjectModalCurrentProject()) {
    return !isYouTrackProject(project) && isProjectModalYouTrackEnabled(project);
}

function isProjectModalYouTrackRemovalRequested(project = getProjectModalCurrentProject()) {
    return isYouTrackProject(project) && !isProjectModalYouTrackEnabled(project);
}

function syncProjectModalYouTrackControls(project = getProjectModalCurrentProject()) {
    if (!dom.projectModalYouTrackBlock || !dom.projectModalCreateInYouTrack) {
        return;
    }

    const linkedToYouTrack = isYouTrackProject(project);
    const enabled = isProjectModalYouTrackEnabled(project);
    dom.projectModalYouTrackBlock.hidden = false;
    dom.projectModalCreateInYouTrack.disabled = false;

    if (dom.projectModalYouTrackNote) {
        if (linkedToYouTrack && enabled) {
            dom.projectModalYouTrackNote.textContent = "Ce projet est deja cree dans YouTrack. Vous pouvez gerer ses taches ci-dessous, ou desactiver le toggle pour l'archiver dans YouTrack a la validation tout en conservant les informations locales.";
        } else if (linkedToYouTrack) {
            dom.projectModalYouTrackNote.textContent = "A la validation, le projet sera archive dans YouTrack. Il pourra etre restaure avec ses taches, tandis que les informations locales du detail projet seront conservees.";
        } else if (enabled) {
            dom.projectModalYouTrackNote.textContent = "Activez ce toggle pour creer le projet dans YouTrack avec le titre, l'ID et la description. Si un projet YouTrack archive avec le meme identifiant existe deja, il sera restaure avec ses taches.";
        } else {
            dom.projectModalYouTrackNote.textContent = "Projet local uniquement. Activez le toggle pour le creer dans YouTrack.";
        }
    }

    syncProjectModalYouTrackBadge(project);
}

function upsertProjectInState(project) {
    const projectIndex = state.projects.findIndex((item) => item.id === project.id);
    if (projectIndex >= 0) {
        state.projects.splice(projectIndex, 1, project);
        return;
    }

    state.projects.push(project);
}

function normalizeProjectTeamMember(member) {
    const ringId = String(member?.ringId || member?.id || "").trim();
    if (!ringId) {
        return null;
    }

    const displayName = String(member?.displayName || member?.name || member?.login || ringId).trim();
    return {
        id: ringId,
        ringId,
        youtrackId: String(member?.youtrackId || "").trim(),
        displayName: displayName || ringId,
        login: String(member?.login || "").trim(),
        email: String(member?.email || "").trim(),
        service: String(member?.service || "").trim(),
    };
}

function getProjectTeamMembers(project = null) {
    const rawMembers = Array.isArray(project?.teamMembers) ? project.teamMembers : [];
    return rawMembers
        .map((member) => normalizeProjectTeamMember(member))
        .filter(Boolean);
}

function normalizeProjectOwner(project) {
    const ownerId = String(project?.ownerId || "").trim();
    const ownerDisplayName = String(project?.ownerDisplayName || "").trim();
    const ownerEmail = String(project?.ownerEmail || "").trim();

    if (!ownerId && !ownerDisplayName && !ownerEmail) {
        return null;
    }

    return {
        id: ownerId,
        displayName: ownerDisplayName,
        email: ownerEmail,
    };
}

function isDynamicProjectTaskColumnKey(columnKey) {
    return /^cf__[A-Za-z0-9_-]+$/.test(String(columnKey || "").trim());
}

function normalizeProjectTaskCustomFieldColumn(column) {
    const key = String(column?.key || "").trim();
    if (!isDynamicProjectTaskColumnKey(key)) {
        return null;
    }

    const fieldName = String(column?.fieldName || "").trim();
    const label = String(column?.label || fieldName || key.replace(/^cf__/, "")).trim();
    const options = Array.isArray(column?.options)
        ? column.options
            .map((option) => ({
                id: String(option?.id || "").trim(),
                name: String(option?.name || option?.presentation || "").trim(),
                presentation: String(option?.presentation || option?.name || "").trim(),
            }))
            .filter((option) => option.name)
        : [];

    return {
        key,
        fieldName,
        label: label || key,
        type: String(column?.type || "").trim(),
        issueType: String(column?.issueType || "").trim(),
        inputKind: String(column?.inputKind || "").trim(),
        emptyFieldText: String(column?.emptyFieldText || "").trim(),
        options,
        ordinal: Number.isFinite(Number(column?.ordinal)) ? Number(column.ordinal) : 0,
    };
}

function setProjectModalCustomFieldColumns(columns) {
    const seenKeys = new Set();
    const normalizedColumns = [];

    (Array.isArray(columns) ? columns : []).forEach((column) => {
        const normalizedColumn = normalizeProjectTaskCustomFieldColumn(column);
        if (!normalizedColumn || seenKeys.has(normalizedColumn.key)) {
            return;
        }

        seenKeys.add(normalizedColumn.key);
        normalizedColumns.push(normalizedColumn);
    });

    projectModalYouTrackTasksState.customFieldColumns = normalizedColumns;
}

function getProjectTaskColumnOptions() {
    const options = [...PROJECT_TASK_COLUMN_OPTIONS];
    const seenKeys = new Set(options.map((option) => option.key));

    projectModalYouTrackTasksState.customFieldColumns.forEach((column) => {
        if (seenKeys.has(column.key)) {
            return;
        }

        seenKeys.add(column.key);
        options.push({
            key: column.key,
            label: column.label,
            fieldName: column.fieldName,
            type: column.type,
            issueType: column.issueType,
            inputKind: column.inputKind,
            emptyFieldText: column.emptyFieldText,
            options: Array.isArray(column.options) ? column.options : [],
            ordinal: column.ordinal,
        });
    });

    return options;
}

function getProjectTaskColumnOption(columnKey) {
    return getProjectTaskColumnOptions().find((option) => option.key === columnKey) || null;
}

function getProjectTaskColumns(project = null) {
    const rawColumns = Array.isArray(project?.taskColumns) ? project.taskColumns : [];
    const normalizedColumns = rawColumns.filter((column) => (
        PROJECT_TASK_COLUMN_OPTIONS.some((option) => option.key === column)
        || isDynamicProjectTaskColumnKey(column)
    ));
    if (!normalizedColumns.includes("summary")) {
        normalizedColumns.unshift("summary");
    }
    return normalizedColumns.length ? normalizedColumns : [...DEFAULT_PROJECT_TASK_COLUMNS];
}

function getProjectModalTeamMembers() {
    return projectModalTeamState.members.map((member) => ({ ...member }));
}

function normalizeComparableUserValue(value) {
    return String(value || "")
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("fr-FR");
}

function getCurrentAuthenticatedUser() {
    return state.currentUser && typeof state.currentUser === "object"
        ? state.currentUser
        : null;
}

function getCurrentAuthenticatedProjectUser() {
    const currentUser = getCurrentAuthenticatedUser();
    if (!currentUser) {
        return null;
    }

    const currentEmail = normalizeComparableUserValue(currentUser.email);
    const currentUsername = normalizeComparableUserValue(currentUser.username || currentUser.id);
    const currentDisplayName = normalizeComparableUserValue(currentUser.displayName);

    return state.projectUsers.find((user) => {
        const userEmail = normalizeComparableUserValue(user?.email);
        const userLogin = normalizeComparableUserValue(user?.login);
        const userDisplayName = normalizeComparableUserValue(user?.displayName);

        return (
            (currentEmail && userEmail === currentEmail)
            || (currentUsername && userLogin === currentUsername)
            || (currentDisplayName && userDisplayName === currentDisplayName)
        );
    }) || null;
}

function doesTeamMemberMatchCurrentUser(member, currentUser, currentProjectUser = null) {
    const memberId = normalizeComparableUserValue(member?.id || member?.ringId);
    const memberYouTrackId = normalizeComparableUserValue(member?.youtrackId);
    const memberEmail = normalizeComparableUserValue(member?.email);
    const memberLogin = normalizeComparableUserValue(member?.login);
    const memberDisplayName = normalizeComparableUserValue(member?.displayName);

    const currentEmail = normalizeComparableUserValue(currentUser?.email);
    const currentUsername = normalizeComparableUserValue(currentUser?.username || currentUser?.id);
    const currentDisplayName = normalizeComparableUserValue(currentUser?.displayName);

    const currentProjectUserId = normalizeComparableUserValue(currentProjectUser?.id || currentProjectUser?.ringId);
    const currentProjectUserYouTrackId = normalizeComparableUserValue(currentProjectUser?.youtrackId);
    const currentProjectUserEmail = normalizeComparableUserValue(currentProjectUser?.email);
    const currentProjectUserLogin = normalizeComparableUserValue(currentProjectUser?.login);

    return (
        (currentEmail && memberEmail && currentEmail === memberEmail)
        || (currentProjectUserEmail && memberEmail && currentProjectUserEmail === memberEmail)
        || (currentUsername && memberLogin && currentUsername === memberLogin)
        || (currentProjectUserLogin && memberLogin && currentProjectUserLogin === memberLogin)
        || (currentDisplayName && memberDisplayName && currentDisplayName === memberDisplayName)
        || (currentProjectUserId && memberId && currentProjectUserId === memberId)
        || (currentProjectUserYouTrackId && memberYouTrackId && currentProjectUserYouTrackId === memberYouTrackId)
    );
}

function doesProjectOwnerMatchCurrentUser(owner, currentUser, currentProjectUser = null) {
    const ownerId = normalizeComparableUserValue(owner?.id);
    const ownerEmail = normalizeComparableUserValue(owner?.email);
    const ownerDisplayName = normalizeComparableUserValue(owner?.displayName);

    const currentEmail = normalizeComparableUserValue(currentUser?.email);
    const currentUsername = normalizeComparableUserValue(currentUser?.username || currentUser?.id);
    const currentDisplayName = normalizeComparableUserValue(currentUser?.displayName);

    const currentProjectUserEmail = normalizeComparableUserValue(currentProjectUser?.email);
    const currentProjectUserLogin = normalizeComparableUserValue(currentProjectUser?.login);
    const currentProjectUserDisplayName = normalizeComparableUserValue(currentProjectUser?.displayName);

    return (
        (ownerEmail && currentEmail && ownerEmail === currentEmail)
        || (ownerEmail && currentProjectUserEmail && ownerEmail === currentProjectUserEmail)
        || (ownerId && currentUsername && ownerId === currentUsername)
        || (ownerId && currentProjectUserLogin && ownerId === currentProjectUserLogin)
        || (ownerDisplayName && currentDisplayName && ownerDisplayName === currentDisplayName)
        || (ownerDisplayName && currentProjectUserDisplayName && ownerDisplayName === currentProjectUserDisplayName)
    );
}

function canCurrentUserManageProjectTeam(project = getProjectModalCurrentProject()) {
    const currentUser = getCurrentAuthenticatedUser();
    if (!currentUser) {
        return false;
    }

    const owner = normalizeProjectOwner(project);
    const currentProjectUser = getCurrentAuthenticatedProjectUser();
    if (owner && doesProjectOwnerMatchCurrentUser(owner, currentUser, currentProjectUser)) {
        return true;
    }

    if (getYouTrackProjectKey(project)) {
        return Boolean(projectModalTeamState.canManage);
    }

    return owner === null;
}

function canCurrentUserEditProjectTaskTable(teamMembers = getProjectModalTeamMembers()) {
    const currentUser = getCurrentAuthenticatedUser();
    if (!currentUser) {
        return false;
    }

    const members = Array.isArray(teamMembers) ? teamMembers : [];
    if (!members.length) {
        return false;
    }

    const currentProjectUser = getCurrentAuthenticatedProjectUser();
    return members.some((member) => doesTeamMemberMatchCurrentUser(member, currentUser, currentProjectUser));
}

function setProjectModalTeamMembers(members, options = {}) {
    const shouldRerenderTasks = options.rerenderTasks !== false;
    const nextMembers = [];
    const seenIds = new Set();

    (Array.isArray(members) ? members : []).forEach((member) => {
        const normalizedMember = normalizeProjectTeamMember(member);

        if (!normalizedMember || seenIds.has(normalizedMember.id)) {
            return;
        }

        seenIds.add(normalizedMember.id);
        nextMembers.push(normalizedMember);
    });

    projectModalTeamState.members = nextMembers;
    renderProjectModalTeam();

    if (shouldRerenderTasks && !dom.projectModal?.hidden && projectModalYouTrackTasksState.mode !== "hidden") {
        rerenderProjectModalYouTrackTasks();
    }
}

function getProjectModalTaskColumns() {
    const columns = projectModalTaskColumnsState.columns.filter((column) => (
        PROJECT_TASK_COLUMN_OPTIONS.some((option) => option.key === column)
        || isDynamicProjectTaskColumnKey(column)
    ));
    if (!columns.includes("summary")) {
        columns.unshift("summary");
    }
    return [...new Set(columns)];
}

function setProjectModalTaskColumns(columns) {
    const nextColumns = columns.filter((column) => (
        PROJECT_TASK_COLUMN_OPTIONS.some((option) => option.key === column)
        || isDynamicProjectTaskColumnKey(column)
    ));
    if (!nextColumns.includes("summary")) {
        nextColumns.unshift("summary");
    }
    projectModalTaskColumnsState.columns = nextColumns.length ? nextColumns : [...DEFAULT_PROJECT_TASK_COLUMNS];
}

function getAvailableProjectTeamUsers() {
    const usersById = new Map();

    state.projectUsers.forEach((user) => {
        const normalizedUser = normalizeProjectTeamMember(user);
        const id = String(normalizedUser?.id || "").trim();
        if (!id) {
            return;
        }

        usersById.set(id, normalizedUser);
    });

    getProjectModalTeamMembers().forEach((member) => {
        if (!usersById.has(member.id)) {
            usersById.set(member.id, member);
        }
    });

    return [...usersById.values()].sort((left, right) => left.displayName.localeCompare(right.displayName, "fr"));
}

function getProjectTeamBadgeColor(userId) {
    const normalized = String(userId || "");
    let hash = 0;
    for (let index = 0; index < normalized.length; index += 1) {
        hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
        hash |= 0;
    }

    const hue = Math.abs(hash) % 360;
    return `hsl(${hue} 72% 56%)`;
}

function filterProjectTeamUsers(users, query) {
    const normalizedQuery = normalizeComparableUserValue(query);
    if (!normalizedQuery) {
        return Array.isArray(users) ? users : [];
    }

    return (Array.isArray(users) ? users : []).filter((user) => {
        const haystack = [
            user?.displayName,
            user?.login,
            user?.email,
            user?.service,
            user?.id,
        ].map((value) => normalizeComparableUserValue(value)).join(" ");

        return haystack.includes(normalizedQuery);
    });
}

function renderProjectModalTeam() {
    if (!dom.projectModalTeamMenu || !dom.projectModalTeamBadges) {
        return;
    }

    const activeElement = document.activeElement;
    const shouldRestoreFilterFocus = activeElement instanceof HTMLInputElement
        && activeElement.matches("[data-project-team-filter]");
    const filterSelectionStart = shouldRestoreFilterFocus ? activeElement.selectionStart : null;
    const filterSelectionEnd = shouldRestoreFilterFocus ? activeElement.selectionEnd : null;

    const canManage = canCurrentUserManageProjectTeam();
    const selectedIds = new Set(getProjectModalTeamMembers().map((member) => member.id));
    const availableUsers = getAvailableProjectTeamUsers();
    const filteredUsers = filterProjectTeamUsers(availableUsers, projectModalTeamState.filterQuery);
    const teamDropdown = dom.projectModalTeamMenu.closest(".project-modal-team-dropdown");
    const teamSummary = teamDropdown?.querySelector(".project-modal-team-summary");

    if (teamDropdown) {
        teamDropdown.classList.toggle("is-readonly", !canManage);
        if (!canManage) {
            teamDropdown.removeAttribute("open");
        }
    }

    if (teamSummary) {
        teamSummary.setAttribute("aria-disabled", String(!canManage));
        teamSummary.title = canManage
            ? "Modifier l'équipe du projet"
            : "Lecture seule : seul le responsable du projet peut modifier l'équipe.";
    }

    const filterMarkup = `
        <div class="project-modal-team-filter-wrap">
            <input
                class="project-modal-team-filter"
                type="search"
                value="${escapeHtml(projectModalTeamState.filterQuery)}"
                placeholder="Filtrer un utilisateur..."
                autocomplete="off"
                data-project-team-filter
            >
        </div>
    `;

    dom.projectModalTeamMenu.innerHTML = availableUsers.length ? `
        ${filterMarkup}
        ${filteredUsers.length ? filteredUsers.map((user) => `
        <label class="project-modal-team-option">
            <input type="checkbox" value="${escapeHtml(user.id)}" data-project-team-user ${selectedIds.has(user.id) ? "checked" : ""} ${canManage ? "" : "disabled"}>
            <span>${escapeHtml(user.displayName)}</span>
        </label>
        `).join("") : `
            <div class="project-modal-team-empty">Aucun utilisateur ne correspond au filtre.</div>
        `}
    ` : `
        <div class="project-modal-team-empty">Aucun utilisateur YouTrack disponible.</div>
    `;

    const badges = getProjectModalTeamMembers();
    dom.projectModalTeamBadges.innerHTML = badges.length ? badges.map((member) => `
        <span class="project-modal-team-badge" style="--team-badge-color:${escapeHtml(getProjectTeamBadgeColor(member.id))};">
            ${escapeHtml(member.displayName)}
        </span>
    `).join("") : `<span class="project-modal-team-empty-badge">-</span>`;

    if (shouldRestoreFilterFocus) {
        requestAnimationFrame(() => {
            const nextFilterInput = dom.projectModalTeamMenu?.querySelector("[data-project-team-filter]");
            if (!(nextFilterInput instanceof HTMLInputElement)) {
                return;
            }

            nextFilterInput.focus();
            if (
                Number.isInteger(filterSelectionStart)
                && Number.isInteger(filterSelectionEnd)
            ) {
                nextFilterInput.setSelectionRange(filterSelectionStart, filterSelectionEnd);
            }
        });
    }
}

function updateCurrentProjectTeamMembers(teamMembers) {
    const currentProject = getProjectModalCurrentProject();
    if (!currentProject) {
        return;
    }

    const updatedProject = normalizeProjectForState({
        ...currentProject,
        teamMembers,
    });

    upsertProjectInState(updatedProject);
    persistState();
}

async function syncProjectModalYouTrackTeam(project = getProjectModalCurrentProject()) {
    const projectKey = getYouTrackProjectKey(project);
    if (!projectKey) {
        projectModalTeamState.canManage = canCurrentUserManageProjectTeam(project);
        renderProjectModalTeam();
        return;
    }

    const requestToken = ++projectModalTeamRequestToken;

    try {
        const payload = await loadYouTrackProjectTeam(projectKey);
        if (requestToken !== projectModalTeamRequestToken) {
            return;
        }

        projectModalTeamState.canManage = Boolean(payload?.canManage);
        const teamMembers = getProjectTeamMembers({ teamMembers: payload?.team || [] });
        setProjectModalTeamMembers(teamMembers, {
            rerenderTasks: projectModalYouTrackTasksState.mode !== "remote" || projectModalYouTrackTasksState.tasks.length > 0,
        });
        updateCurrentProjectTeamMembers(teamMembers);
    } catch (error) {
        if (requestToken !== projectModalTeamRequestToken) {
            return;
        }

        console.error(error);
        showProjectModalError(error.message || "Impossible de charger l'équipe YouTrack du projet.");
    }
}

async function onProjectModalTeamMenuChange(event) {
    const checkbox = event.target.closest("[data-project-team-user]");
    if (!checkbox) {
        return;
    }

    if (!canCurrentUserManageProjectTeam()) {
        checkbox.checked = !checkbox.checked;
        showProjectModalError("Lecture seule : seul le responsable du projet peut modifier l'équipe.");
        return;
    }

    const userId = String(checkbox.value || "").trim();
    const availableUsers = getAvailableProjectTeamUsers();
    const user = availableUsers.find((item) => item.id === userId);
    if (!user) {
        return;
    }

    const nextMembers = getProjectModalTeamMembers();
    if (checkbox.checked) {
        if (!nextMembers.some((member) => member.id === userId)) {
            nextMembers.push(user);
        }
    } else {
        const nextIndex = nextMembers.findIndex((member) => member.id === userId);
        if (nextIndex >= 0) {
            nextMembers.splice(nextIndex, 1);
        }
    }

    const project = getProjectModalCurrentProject();
    if (isYouTrackProject(project)) {
        checkbox.disabled = true;
        hideProjectModalError();

        try {
            const payload = checkbox.checked
                ? await addYouTrackProjectTeamMember(project.youtrackId, userId)
                : await removeYouTrackProjectTeamMember(project.youtrackId, userId);
            projectModalTeamState.canManage = Boolean(payload?.canManage ?? true);
            const syncedMembers = getProjectTeamMembers({ teamMembers: payload?.team || [] });
            setProjectModalTeamMembers(syncedMembers);
            updateCurrentProjectTeamMembers(syncedMembers);
            await syncProjectModalYouTrackTasks({
                ...project,
                teamMembers: syncedMembers,
            });
        } catch (error) {
            console.error(error);
            showProjectModalError(error.message || "Impossible de mettre à jour l'équipe YouTrack.");
            checkbox.checked = !checkbox.checked;
            renderProjectModalTeam();
        } finally {
            checkbox.disabled = false;
        }

        return;
    }

    projectModalTeamState.canManage = canCurrentUserManageProjectTeam(project);
    setProjectModalTeamMembers(nextMembers);
}

function onProjectModalTeamMenuInput(event) {
    const filterInput = event.target.closest("[data-project-team-filter]");
    if (!filterInput) {
        return;
    }

    projectModalTeamState.filterQuery = String(filterInput.value || "");
    renderProjectModalTeam();
}

function renderProjectModalYouTrackColumnsMenu() {
    if (!dom.projectModalYouTrackColumnsMenu) {
        return;
    }

    const visibleColumns = new Set(getProjectModalTaskColumns());
    dom.projectModalYouTrackColumnsMenu.innerHTML = getProjectTaskColumnOptions().map((column) => `
        <label class="project-modal-youtrack-column-option">
            <input type="checkbox" value="${escapeHtml(column.key)}" data-project-task-column ${visibleColumns.has(column.key) ? "checked" : ""}>
            <span>${escapeHtml(column.label)}</span>
        </label>
    `).join("");
}

function onProjectModalYouTrackColumnsMenuChange(event) {
    const checkbox = event.target.closest("[data-project-task-column]");
    if (!checkbox) {
        return;
    }

    const selectedColumns = [...dom.projectModalYouTrackColumnsMenu.querySelectorAll("[data-project-task-column]:checked")]
        .map((input) => String(input.value || "").trim())
        .filter(Boolean);

    setProjectModalTaskColumns(selectedColumns);
    rerenderProjectModalYouTrackTasks();
}

function onProjectModalYouTrackToggleChange() {
    if (dom.projectModalCreateInYouTrack?.checked) {
        setProjectModalDescriptionExpanded(true);
    } else if (!getYouTrackProjectKey(getProjectModalCurrentProject())) {
        projectModalYouTrackTasksState.pendingTasks = [];
        resetProjectModalYouTrackTaskDraftState();
    }

    syncProjectModalYouTrackControls();
    syncProjectModalYouTrackTasks(getProjectModalCurrentProject());
}

async function onProjectModalSubmit(event) {
    event.preventDefault();

    const mode = getProjectModalMode();
    const projectId = dom.projectModal.dataset.projectId || "";
    const project = mode === "edit" ? findProject(projectId) : null;
    if (mode === "edit" && !project) {
        closeProjectModal();
        return;
    }

    const projectDraft = project ? { ...project } : createEmptyProjectDraft();
    const titleValue = dom.projectModalTitleInput.value.trim();
    const refValue = dom.projectModalRefInput.value.trim();
    const serviceValue = dom.projectModalServiceInput.value.trim();
    const typeValue = normalizeProjectType(dom.projectModalTypeInput.value);
    const parentProjectId = normalizeProjectParentId(dom.projectModalParentInput.value, projectDraft.id || projectId);
    const parentProject = parentProjectId ? findProject(parentProjectId) : null;
    const riskGainValue = normalizeProjectMetaInput(dom.projectModalRiskGainInput.value);
    const budgetValue = normalizeProjectMetaInput(dom.projectModalBudgetInput.value);
    const prioritizationValue = normalizeProjectMetaInput(dom.projectModalPrioritizationInput.value);
    const statusValue = normalizeProjectStoredStatus(dom.projectModalStatusInput.value, project);
    const progressionValue = normalizeProjectProgression(dom.projectModalProgressInput.value);
    const descriptionValue = dom.projectModalDescriptionInput.value.trim();
    const startValue = normalizeDateInputValue(dom.projectModalStartInput.value);
    const endValue = normalizeDateInputValue(dom.projectModalEndInput.value);
    const createInYouTrack = isProjectModalYouTrackRequested(project);
    const removeFromYouTrack = isProjectModalYouTrackRemovalRequested(project);
    const pendingYouTrackTasks = getPendingProjectModalYouTrackTasksPayload();

    if (!setProjectColorFromValue(projectDraft, dom.projectModalColorHexInput.value)) {
        showProjectModalError("La couleur hexadécimale doit être au format #fff000 ou #ff0.");
        dom.projectModalColorHexInput.value = projectDraft.customColor || "";
        return;
    }

    if (!titleValue) {
        showProjectModalError("Renseignez le titre du projet.");
        return;
    }

    if (!refValue) {
        showProjectModalError("Renseignez l'identifiant du projet.");
        return;
    }

    if (!serviceValue) {
        showProjectModalError("Renseignez le service du projet.");
        return;
    }

    if (parentProjectId && !parentProject) {
        showProjectModalError("Le projet parent sélectionné est introuvable.");
        return;
    }

    if (parentProjectId && wouldCreateProjectCycle(projectDraft.id || projectId, parentProjectId)) {
        showProjectModalError("Un projet ne peut pas être rattaché à l'un de ses sous-projets.");
        return;
    }

    if ((startValue && !endValue) || (!startValue && endValue)) {
        showProjectModalError("Renseignez une date de début et une date de fin, ou laissez les deux champs vides.");
        return;
    }

    if (createInYouTrack && !descriptionValue) {
        showProjectModalError("La description est obligatoire pour créer le projet dans YouTrack.");
        return;
    }

    projectDraft.ref = refValue;
    projectDraft.title = titleValue;
    projectDraft.service = serviceValue;
    projectDraft.parentProjectId = parentProjectId || null;
    projectDraft.projectType = typeValue || null;
    projectDraft.description = descriptionValue;
    projectDraft.riskGain = riskGainValue;
    projectDraft.budgetEstimate = budgetValue;
    projectDraft.prioritization = prioritizationValue;
    projectDraft.status = statusValue;
    projectDraft.progression = progressionValue;
    projectDraft.teamMembers = getProjectModalTeamMembers();
    projectDraft.taskColumns = getProjectModalTaskColumns();

    if (!startValue && !endValue) {
        clearProjectSchedule(projectDraft, { cascadeChildren: false, skipLaneNormalization: true });
        if (mode === "create") {
            projectDraft.status = "A planifier";
        }
    } else if (startValue && endValue) {
        if (startDateToDate(startValue) > startDateToDate(endValue)) {
            showProjectModalError("La date de fin doit être postérieure ou égale à la date de début.");
            return;
        }

        const startSlot = snapDateToHalfMonthStart(startValue);
        const endSlot = snapDateToHalfMonthStart(endValue);
        const duration = getHalfMonthSlotNumber(endSlot) - getHalfMonthSlotNumber(startSlot) + 1;

        projectDraft.start = startSlot;
        projectDraft.duration = duration;
        projectDraft.startExact = startValue;
        projectDraft.endExact = endValue;
        projectDraft.lane = Number.isFinite(projectDraft.lane) ? projectDraft.lane : getNextLane();

        if (normalizeProjectStoredStatus(projectDraft.status, projectDraft) === "A planifier") {
            projectDraft.status = "Planifié";
        }
    }

    if (projectDraft.parentProjectId) {
        applyProjectParentSchedule(projectDraft, parentProject, { allowAutoSchedule: true });
    }

    if (removeFromYouTrack) {
        projectDraft.youtrackId = null;
        projectDraft.youtrackUrl = null;
    }

    dom.projectModalSubmitButton.disabled = true;

    try {
        const response = await createProjectRecord(buildProjectPersistencePayload(projectDraft), createInYouTrack, removeFromYouTrack);
        const savedProject = normalizeProjectForState(response?.project || projectDraft);
        upsertProjectInState(savedProject);
        syncProjectScheduleHierarchy(savedProject);
        populateServiceFilter();
        persistState();
        render();

        if (createInYouTrack && pendingYouTrackTasks.length) {
            try {
                await createPendingYouTrackTasks(savedProject.youtrackId, pendingYouTrackTasks);
            } catch (error) {
                console.error(error);
                openProjectModal(savedProject);
                showProjectModalError(error.message || "Le projet YouTrack a été créé, mais les tâches n'ont pas pu être ajoutées.");
                return;
            }
        }

        closeProjectModal();
    } catch (error) {
        console.error(error);
        showProjectModalError(error.message || "Impossible d'enregistrer le projet.");
    } finally {
        dom.projectModalSubmitButton.disabled = false;
    }
}

function onProjectModalColorChange() {
    dom.projectModalColorHexInput.value = normalizeProjectColorHex(dom.projectModalColorInput.value) || "";
    hideProjectModalError();
    syncProjectModalDisplays();
}

function onProjectModalColorHexChange() {
    const normalizedColor = normalizeProjectColorHex(dom.projectModalColorHexInput.value);
    if (normalizedColor === null) {
        showProjectModalError("La couleur hexadécimale doit être au format #fff000 ou #ff0.");
        return;
    }

    if (normalizedColor === "") {
        dom.projectModalColorInput.value = getDefaultColor(dom.projectModalServiceInput.value.trim() || "Non renseigné");
    } else {
        dom.projectModalColorHexInput.value = normalizedColor;
        dom.projectModalColorInput.value = normalizedColor;
    }

    hideProjectModalError();
    syncProjectModalDisplays();
}

function onProjectModalClear() {
    const projectId = dom.projectModal.dataset.projectId || "";
    const project = findProject(projectId);
    if (!project) {
        closeProjectModal();
        return;
    }

    clearProjectSchedule(project);
    populateServiceFilter();
    persistState();
    render();
    closeProjectModal();
}

async function onProjectModalDelete() {
    const projectId = dom.projectModal.dataset.projectId || "";
    const project = findProject(projectId);
    if (!project) {
        closeProjectModal();
        return;
    }

    const confirmationMessage = isYouTrackProject(project)
        ? `Supprimer définitivement le projet "${project.title}" ? Il sera supprimé localement et archivé dans YouTrack, avec ses tâches conservées pour une éventuelle restauration.`
        : `Supprimer définitivement le projet "${project.title}" ?`;
    const confirmed = window.confirm(confirmationMessage);
    if (!confirmed) {
        return;
    }

    dom.projectModalDeleteButton.disabled = true;

    try {
        await deleteProject(project.id);
        state.projects = state.projects
            .filter((item) => item.id !== project.id)
            .map((item) => ({
                ...item,
                parentProjectId: item.parentProjectId === project.id ? null : item.parentProjectId
            }));
        sanitizeExpandedProjectIds();
        populateServiceFilter();
        persistState();
        render();
        closeProjectModal();
    } catch (error) {
        console.error(error);
        showProjectModalError(error.message || "Impossible de supprimer le projet.");
    } finally {
        dom.projectModalDeleteButton.disabled = false;
    }
}

function onProjectModalParentChange() {
    const selectedParentProject = getProjectModalSelectedParentProject(dom.projectModal.dataset.projectId || "");
    const startValue = normalizeDateInputValue(dom.projectModalStartInput.value);
    const endValue = normalizeDateInputValue(dom.projectModalEndInput.value);

    if ((!startValue || !endValue) && isScheduled(selectedParentProject)) {
        const parentDates = getProjectEditableDates(selectedParentProject);
        dom.projectModalStartInput.value = parentDates.start || "";
        dom.projectModalEndInput.value = parentDates.end || "";
    }

    syncProjectModalDateBounds();
}

function syncProjectModalDateBounds() {
    const startValue = normalizeDateInputValue(dom.projectModalStartInput.value);
    const endValue = normalizeDateInputValue(dom.projectModalEndInput.value);
    const selectedParentProject = getProjectModalSelectedParentProject(dom.projectModal.dataset.projectId || "");
    const parentDates = isScheduled(selectedParentProject)
        ? getProjectEditableDates(selectedParentProject)
        : { start: "", end: "" };

    dom.projectModalStartInput.min = parentDates.start || "";
    dom.projectModalStartInput.max = endValue || parentDates.end || "";
    dom.projectModalEndInput.min = startValue || parentDates.start || "";
    dom.projectModalEndInput.max = parentDates.end || "";
    dom.projectModalHelp.textContent = getProjectModalParentHelpMessage(selectedParentProject);
    syncProjectModalDisplays();
}

function showProjectModalError(message) {
    dom.projectModalError.textContent = message;
    dom.projectModalError.hidden = false;
}

function hideProjectModalError() {
    dom.projectModalError.hidden = true;
    dom.projectModalError.textContent = "";
}

function onProjectModalFormClick(event) {
    if (event.target.closest("#projectModalDescriptionToggle")) {
        return;
    }

    if (event.target.closest(".project-modal-team-summary") && !canCurrentUserManageProjectTeam()) {
        event.preventDefault();
        showProjectModalError("Lecture seule : seul le responsable du projet peut modifier l'équipe.");
        return;
    }

    if (event.target.closest(".project-modal-team, .project-modal-youtrack-columns")) {
        return;
    }

    if (event.target.closest(".project-modal-editor")) {
        return;
    }

    const item = event.target.closest("[data-editable-item]");
    if (!item) {
        return;
    }

    openProjectModalEditor(item);
}

function onProjectModalFormFocusOut(event) {
    const item = event.target.closest("[data-editable-item]");
    if (!item) {
        return;
    }

    const nextTarget = event.relatedTarget;
    if (nextTarget instanceof HTMLElement && item.contains(nextTarget)) {
        return;
    }

    item.classList.remove("is-editing");
}

function resetPlanning() {
    const confirmed = window.confirm("Réinitialiser la timeline et remettre tous les projets dans la liste à planifier ?");
    if (!confirmed) {
        return;
    }

    state.settings = { ...DEFAULT_SETTINGS };
    state.projects = state.projects.map((project) => ({
        ...project,
        customColor: "",
        color: getDefaultColor(project.service),
        start: null,
        duration: null,
        lane: null,
        startExact: null,
        endExact: null
    }));

    localStorage.removeItem(STORAGE_KEY);
    populateServiceFilter();
    render();
}

function getBarMetrics(project) {
    const left = getTimelineSlotIndex(project.start) * getHalfMonthWidth();
    const width = project.duration * getHalfMonthWidth();
    const isOutside = left + width <= 0 || left >= state.settings.visibleMonths * getScaledMonthWidth();
    return { left, width, isOutside };
}

function buildVisibleMonths() {
    return Array.from({ length: state.settings.visibleMonths }, (_, index) => {
        const yearMonth = addMonths(state.settings.timelineStart, index);
        const date = yearMonthToDate(yearMonth);
        const rawLabel = monthShortFormatter.format(date).replace(".", "");
        return {
            year: date.getFullYear(),
            shortLabel: rawLabel.charAt(0).toUpperCase() + rawLabel.slice(1),
            title: monthLongFormatter.format(date),
            yearMonth
        };
    });
}

function groupMonthsByYear(months) {
    return months.reduce((groups, month) => {
        const lastGroup = groups.at(-1);
        if (lastGroup && lastGroup.year === month.year) {
            lastGroup.count += 1;
        } else {
            groups.push({ year: month.year, count: 1 });
        }
        return groups;
    }, []);
}

function matchesFilters(project) {
    const search = state.settings.search.trim().toLowerCase();
    const serviceFilter = state.settings.serviceFilter;
    const typeFilter = state.settings.typeFilter;
    const statusFilter = state.settings.statusFilter;
    const serviceMatches = serviceFilter === "all" || tokenizeService(project.service).includes(serviceFilter);
    const projectType = normalizeProjectType(project.projectType);
    const typeMatches = typeFilter === "all"
        || (typeFilter === EMPTY_PROJECT_TYPE_FILTER && !projectType)
        || projectType === typeFilter;
    const statusMatches = statusFilter === "all" || getProjectEffectiveStatus(project) === statusFilter;

    if (!serviceMatches || !typeMatches || !statusMatches) {
        return false;
    }

    if (!search) {
        return true;
    }

    const haystack = `${project.ref} ${project.title} ${project.service} ${projectType || ""}`.toLowerCase();
    return haystack.includes(search);
}

function sortProjectsByLaneThenRef(left, right) {
    const laneDifference = (left.lane ?? Number.MAX_SAFE_INTEGER) - (right.lane ?? Number.MAX_SAFE_INTEGER);
    if (laneDifference !== 0) {
        return laneDifference;
    }

    return String(left.ref || "").localeCompare(String(right.ref || ""), "fr");
}

function normalizeExpandedProjectIds(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return [...new Set(
        value
            .map((item) => String(item || "").trim())
            .filter(Boolean)
    )];
}

function normalizeProjectParentId(value, currentProjectId = "") {
    const normalizedValue = String(value || "").trim();
    if (!normalizedValue || normalizedValue === String(currentProjectId || "").trim()) {
        return null;
    }

    return normalizedValue;
}

function normalizeProjectType(value) {
    const normalizedValue = String(value || "").trim();
    return PROJECT_TYPES.includes(normalizedValue) ? normalizedValue : "";
}

function formatProjectParentLabel(project) {
    if (!project) {
        return "Aucun";
    }

    const projectRef = String(project.ref || "").trim();
    const projectTitle = String(project.title || "").trim();
    if (!projectRef) {
        return projectTitle || "Aucun";
    }

    return projectTitle ? `${projectRef} - ${projectTitle}` : projectRef;
}

function renderProjectTypeOptions(selectedType = "") {
    const normalizedType = normalizeProjectType(selectedType);
    return [
        `<option value="">Non renseigné</option>`,
        ...PROJECT_TYPES.map((type) => `<option value="${escapeHtml(type)}"${type === normalizedType ? " selected" : ""}>${escapeHtml(type)}</option>`)
    ].join("");
}

function getProjectParent(project, options = {}) {
    const parentProjectId = normalizeProjectParentId(project?.parentProjectId, project?.id || "");
    if (!parentProjectId) {
        return null;
    }

    const projectMap = options.projectMap instanceof Map ? options.projectMap : null;
    const parentProject = projectMap ? (projectMap.get(parentProjectId) || null) : findProject(parentProjectId);
    if (!parentProject) {
        return null;
    }

    if (options.scheduledOnly && !isScheduled(parentProject)) {
        return null;
    }

    return parentProject;
}

function getProjectChildren(projectId, options = {}) {
    const normalizedProjectId = String(projectId || "").trim();
    if (!normalizedProjectId) {
        return [];
    }

    const projectList = Array.isArray(options.projectList) ? options.projectList : state.projects;
    return projectList.filter((project) => {
        if (normalizeProjectParentId(project.parentProjectId, project.id || "") !== normalizedProjectId) {
            return false;
        }

        return options.scheduledOnly ? isScheduled(project) : true;
    });
}

function hasProjectChildren(projectId, options = {}) {
    return getProjectChildren(projectId, options).length > 0;
}

function sanitizeExpandedProjectIds() {
    state.settings.expandedProjectIds = normalizeExpandedProjectIds(state.settings.expandedProjectIds)
        .filter((projectId) => hasProjectChildren(projectId, { scheduledOnly: true }));
}

function isTimelineProjectExpanded(projectId) {
    return normalizeExpandedProjectIds(state.settings.expandedProjectIds).includes(String(projectId || "").trim());
}

function setTimelineProjectExpanded(projectId, expanded, options = {}) {
    const normalizedProjectId = String(projectId || "").trim();
    if (!normalizedProjectId) {
        return;
    }

    const expandedProjectIds = new Set(normalizeExpandedProjectIds(state.settings.expandedProjectIds));
    if (expanded) {
        expandedProjectIds.add(normalizedProjectId);
    } else {
        expandedProjectIds.delete(normalizedProjectId);
    }

    state.settings.expandedProjectIds = Array.from(expandedProjectIds);
    sanitizeExpandedProjectIds();

    if (options.persist !== false) {
        writeSerializableStateToStorage();
    }

    if (options.render !== false) {
        render();
    }
}

function toggleTimelineProjectExpanded(projectId) {
    if (!hasProjectChildren(projectId, { scheduledOnly: true })) {
        return;
    }

    setTimelineProjectExpanded(projectId, !isTimelineProjectExpanded(projectId));
}

function expandTimelineAncestors(projectId) {
    let currentProject = findProject(projectId);
    while (currentProject?.parentProjectId) {
        setTimelineProjectExpanded(currentProject.parentProjectId, true, { persist: false, render: false });
        currentProject = findProject(currentProject.parentProjectId);
    }
}

function buildTimelineHierarchyRows(projects) {
    const projectMap = new Map(projects.map((project) => [project.id, project]));
    const childrenMap = new Map();
    const roots = [];

    projects.forEach((project) => {
        const parentProject = getProjectParent(project, { projectMap });
        if (parentProject) {
            if (!childrenMap.has(parentProject.id)) {
                childrenMap.set(parentProject.id, []);
            }

            childrenMap.get(parentProject.id).push(project);
            return;
        }

        roots.push(project);
    });

    const sortProjects = (projectList) => projectList.slice().sort(sortProjectsByLaneThenRef);
    const rows = [];
    const visitedProjectIds = new Set();

    function walkProject(project, depth) {
        if (!project || visitedProjectIds.has(project.id)) {
            return;
        }

        visitedProjectIds.add(project.id);

        const childProjects = sortProjects(childrenMap.get(project.id) || []);
        const isExpanded = childProjects.length > 0 ? isTimelineProjectExpanded(project.id) : false;
        rows.push({
            project,
            depth,
            hasChildren: childProjects.length > 0,
            expanded: isExpanded
        });

        if (isExpanded) {
            childProjects.forEach((childProject) => {
                walkProject(childProject, depth + 1);
            });
        }
    }

    sortProjects(roots).forEach((project) => {
        walkProject(project, 0);
    });

    return rows;
}

function buildVisibleTimelineProjects() {
    return buildTimelineHierarchyRows(
        state.projects
            .filter(isScheduled)
            .filter(matchesFilters)
    );
}

function normalizeLanes() {
    let nextLane = 0;
    const scheduledProjects = state.projects.filter(isScheduled);
    const projectMap = new Map(scheduledProjects.map((project) => [project.id, project]));
    const childrenMap = new Map();
    const roots = [];

    scheduledProjects.forEach((project) => {
        const parentProject = getProjectParent(project, { projectMap, scheduledOnly: true });
        if (parentProject) {
            if (!childrenMap.has(parentProject.id)) {
                childrenMap.set(parentProject.id, []);
            }

            childrenMap.get(parentProject.id).push(project);
            return;
        }

        roots.push(project);
    });

    const visitedProjectIds = new Set();
    const sortProjects = (projectList) => projectList.slice().sort(sortProjectsByLaneThenRef);

    function assignLane(project) {
        if (!project || visitedProjectIds.has(project.id)) {
            return;
        }

        visitedProjectIds.add(project.id);
        project.lane = nextLane;
        nextLane += 1;

        sortProjects(childrenMap.get(project.id) || []).forEach(assignLane);
    }

    sortProjects(roots).forEach(assignLane);
    sortProjects(scheduledProjects.filter((project) => !visitedProjectIds.has(project.id))).forEach(assignLane);

    state.projects
        .filter((project) => !isScheduled(project))
        .forEach((project) => {
            project.lane = null;
        });
}

function getNextLane() {
    const lanes = state.projects
        .filter(isScheduled)
        .map((project) => project.lane ?? 0);
    return lanes.length ? Math.max(...lanes) + 1 : 0;
}

function isScheduled(project) {
    return Boolean(project?.start) && Number.isFinite(Number(project?.duration)) && Number(project.duration) > 0;
}

function clearProjectSchedule(project, options = {}) {
    const currentStatus = normalizeProjectStoredStatus(project.status, project);

    project.start = null;
    project.duration = null;
    project.lane = null;
    project.startExact = null;
    project.endExact = null;

    if (currentStatus === "Planifié" || currentStatus === "En cours") {
        project.status = "A planifier";
    }

    if (options.cascadeChildren !== false) {
        getProjectChildren(project.id).forEach((childProject) => {
            clearProjectSchedule(childProject, { cascadeChildren: true, skipLaneNormalization: true });
        });
    }

    if (!options.skipLaneNormalization) {
        normalizeLanes();
        sanitizeExpandedProjectIds();
    }
}

function getProjectSubtreeProjects(projectId, options = {}) {
    const normalizedProjectId = String(projectId || "").trim();
    if (!normalizedProjectId) {
        return [];
    }

    const scheduledOnly = options.scheduledOnly === true;
    const subtreeProjects = [];
    const projectIdsToVisit = [normalizedProjectId];
    const visitedProjectIds = new Set();

    while (projectIdsToVisit.length > 0) {
        const currentProjectId = projectIdsToVisit.shift();
        if (!currentProjectId || visitedProjectIds.has(currentProjectId)) {
            continue;
        }

        visitedProjectIds.add(currentProjectId);
        const project = findProject(currentProjectId);
        if (!project) {
            continue;
        }

        if (!scheduledOnly || isScheduled(project)) {
            subtreeProjects.push(project);
        }

        getProjectChildren(currentProjectId, { scheduledOnly }).forEach((childProject) => {
            projectIdsToVisit.push(childProject.id);
        });
    }

    return subtreeProjects;
}

function getProjectSubtreeProjectIds(projectId, options = {}) {
    return new Set(getProjectSubtreeProjects(projectId, options).map((project) => project.id));
}

function getProjectSubtreeMaxLane(projectId) {
    const subtreeProjects = getProjectSubtreeProjects(projectId, { scheduledOnly: true })
        .filter((project) => Number.isFinite(project.lane));

    if (!subtreeProjects.length) {
        return null;
    }

    return Math.max(...subtreeProjects.map((project) => Number(project.lane)));
}

function sanitizeDuration(value) {
    if (!Number.isFinite(Number(value))) {
        return null;
    }
    return Math.max(1, Number(value));
}

function findProject(projectId) {
    return state.projects.find((project) => project.id === projectId);
}

function wouldCreateProjectCycle(projectId, candidateParentId) {
    const normalizedProjectId = String(projectId || "").trim();
    let currentParentId = String(candidateParentId || "").trim();
    if (!normalizedProjectId || !currentParentId) {
        return false;
    }

    const visitedProjectIds = new Set([normalizedProjectId]);
    while (currentParentId) {
        if (visitedProjectIds.has(currentParentId)) {
            return true;
        }

        visitedProjectIds.add(currentParentId);
        currentParentId = String(findProject(currentParentId)?.parentProjectId || "").trim();
    }

    return false;
}

function applyProjectParentSchedule(project, parentProject = getProjectParent(project), options = {}) {
    if (!project?.parentProjectId) {
        return;
    }

    const resolvedParentProject = parentProject || findProject(project.parentProjectId);
    if (!isScheduled(resolvedParentProject)) {
        clearProjectSchedule(project, { cascadeChildren: false, skipLaneNormalization: true });
        return;
    }

    if (!isScheduled(project) && options.allowAutoSchedule !== true) {
        return;
    }

    const parentStartSlot = getHalfMonthSlotNumber(resolvedParentProject.start);
    const parentEndSlot = parentStartSlot + resolvedParentProject.duration - 1;
    let childStartSlot = isScheduled(project) ? getHalfMonthSlotNumber(project.start) : parentStartSlot;
    let childEndSlot = isScheduled(project) ? childStartSlot + project.duration - 1 : parentEndSlot;

    if (childEndSlot < parentStartSlot || childStartSlot > parentEndSlot) {
        childStartSlot = parentStartSlot;
        childEndSlot = parentEndSlot;
    }

    childStartSlot = clamp(childStartSlot, parentStartSlot, parentEndSlot);
    childEndSlot = clamp(childEndSlot, childStartSlot, parentEndSlot);

    project.start = addHalfMonths(resolvedParentProject.start, childStartSlot - parentStartSlot);
    project.duration = childEndSlot - childStartSlot + 1;
    project.lane = Number.isFinite(project.lane) ? project.lane : getNextLane();
    syncProjectExactDatesWithSchedule(project);

    if (normalizeProjectStoredStatus(project.status, project) === "A planifier") {
        project.status = "Planifié";
    }
}

function syncChildProjectSchedules(projectId) {
    const parentProject = findProject(projectId);
    if (!parentProject) {
        return;
    }

    getProjectChildren(projectId)
        .sort(sortProjectsByLaneThenRef)
        .forEach((childProject) => {
            applyProjectParentSchedule(childProject, parentProject, { allowAutoSchedule: true });
            syncChildProjectSchedules(childProject.id);
        });
}

function syncProjectScheduleHierarchy(project) {
    if (!project) {
        return;
    }

    if (project.parentProjectId) {
        applyProjectParentSchedule(project, null, { allowAutoSchedule: true });
    }

    syncChildProjectSchedules(project.id);
    normalizeLanes();
    sanitizeExpandedProjectIds();
}

function getTimelineRowReorderDropTarget(event, draggedProjectId) {
    const normalizedDraggedProjectId = String(draggedProjectId || "").trim();
    if (!normalizedDraggedProjectId) {
        return null;
    }

    const draggedSubtreeProjectIds = getProjectSubtreeProjectIds(normalizedDraggedProjectId, { scheduledOnly: true });
    const rows = Array.from(dom.timelineRows.querySelectorAll(".timeline-row[data-project-id]"));
    let bestCandidate = null;

    rows.forEach((row) => {
        const targetProjectId = String(row.dataset.projectId || "").trim();
        if (!targetProjectId || targetProjectId === normalizedDraggedProjectId || draggedSubtreeProjectIds.has(targetProjectId)) {
            return;
        }

        const rect = row.getBoundingClientRect();
        const insertionThreshold = Math.min(12, Math.max(6, Math.round(rect.height * 0.18)));
        const distanceToTop = Math.abs(event.clientY - rect.top);
        const distanceToBottom = Math.abs(event.clientY - rect.bottom);

        if (distanceToTop <= insertionThreshold) {
            if (!bestCandidate || distanceToTop < bestCandidate.distance) {
                bestCandidate = {
                    row,
                    projectId: targetProjectId,
                    position: "before",
                    distance: distanceToTop
                };
            }
        }

        if (distanceToBottom <= insertionThreshold) {
            if (!bestCandidate || distanceToBottom < bestCandidate.distance) {
                bestCandidate = {
                    row,
                    projectId: targetProjectId,
                    position: "after",
                    distance: distanceToBottom
                };
            }
        }
    });

    if (!bestCandidate) {
        return null;
    }

    return {
        row: bestCandidate.row,
        projectId: bestCandidate.projectId,
        position: bestCandidate.position
    };
}

function moveTimelineProjectRelativeTo(project, targetProject, position) {
    if (!project || !targetProject || !["before", "after"].includes(position)) {
        return false;
    }

    const nextParentProjectId = normalizeProjectParentId(targetProject.parentProjectId, targetProject.id || "");
    if (
        nextParentProjectId
        && (project.id === nextParentProjectId || wouldCreateProjectCycle(project.id, nextParentProjectId))
    ) {
        window.alert("Un projet ne peut pas être déplacé dans l'un de ses propres sous-projets.");
        return false;
    }

    const anchorLane = position === "after"
        ? getProjectSubtreeMaxLane(targetProject.id)
        : targetProject.lane;
    const fallbackLane = Number.isFinite(targetProject.lane) ? Number(targetProject.lane) : getNextLane();

    project.parentProjectId = nextParentProjectId;
    project.lane = position === "before"
        ? (Number.isFinite(anchorLane) ? Number(anchorLane) - 0.5 : fallbackLane - 0.5)
        : (Number.isFinite(anchorLane) ? Number(anchorLane) + 0.5 : fallbackLane + 0.5);

    syncProjectScheduleHierarchy(project);

    if (nextParentProjectId) {
        setTimelineProjectExpanded(nextParentProjectId, true, { persist: false, render: false });
        expandTimelineAncestors(nextParentProjectId);
    }

    return true;
}

function assignProjectToParent(project, parentProject, options = {}) {
    if (!project || !parentProject) {
        return false;
    }

    if (project.id === parentProject.id || wouldCreateProjectCycle(project.id, parentProject.id)) {
        window.alert("Un projet ne peut pas être rattaché à l'un de ses sous-projets.");
        return false;
    }

    project.parentProjectId = parentProject.id;
    applyProjectParentSchedule(project, parentProject, { allowAutoSchedule: true });
    syncProjectScheduleHierarchy(project);

    if (options.expandParent) {
        setTimelineProjectExpanded(parentProject.id, true, { persist: false, render: false });
        expandTimelineAncestors(parentProject.id);
    }

    return true;
}

function detachProjectFromParent(project) {
    if (!project?.parentProjectId) {
        return false;
    }

    project.parentProjectId = null;
    syncProjectScheduleHierarchy(project);
    return true;
}

function isYouTrackProject(project) {
    return Boolean(getYouTrackProjectKey(project));
}

function getYouTrackProjectKey(project) {
    return String(project?.youtrackId || "").trim();
}

function syncProjectModalYouTrackBadge(project) {
    if (!dom.projectModalYouTrackBadge) {
        return;
    }

    dom.projectModalYouTrackBadge.hidden = !isProjectModalYouTrackEnabled(project);
}

function resetProjectModalYouTrackTasks() {
    projectModalYouTrackTasksState.mode = "hidden";
    projectModalYouTrackTasksState.projectKey = "";
    projectModalYouTrackTasksState.tasks = [];
    projectModalYouTrackTasksState.assignees = [];
    projectModalYouTrackTasksState.stateOptions = [];
    projectModalYouTrackTasksState.customFieldColumns = [];
    projectModalYouTrackTasksState.pendingTasks = [];
    resetProjectModalYouTrackTaskDraftState();
    projectModalYouTrackTasksState.editingTaskId = "";
    projectModalYouTrackTasksState.editingField = "";
    projectModalYouTrackTasksState.editingValue = "";
    projectModalYouTrackTasksState.editingSubmitting = false;
    projectModalYouTrackTasksState.editingError = "";

    if (dom.projectModalYouTrackTasks) {
        dom.projectModalYouTrackTasks.hidden = true;
    }

    if (dom.projectModalYouTrackTasksCount) {
        dom.projectModalYouTrackTasksCount.textContent = "";
    }

    if (dom.projectModalYouTrackTasksBody) {
        dom.projectModalYouTrackTasksBody.innerHTML = "";
    }

    delete dom.projectModal.dataset.youtrackProjectKey;

    syncProjectModalTaskAreaLayout(0);
}

async function syncProjectModalYouTrackTasks(project) {
    const projectKey = getYouTrackProjectKey(project);
    if (!dom.projectModalYouTrackTasksBody || !dom.projectModalYouTrackTasks) {
        resetProjectModalYouTrackTasks();
        return;
    }

    if (projectKey && isProjectModalYouTrackEnabled(project)) {
        const requestToken = ++projectModalTasksRequestToken;
        projectModalYouTrackTasksState.mode = "remote";
        projectModalYouTrackTasksState.projectKey = projectKey;
        projectModalYouTrackTasksState.tasks = [];
        projectModalYouTrackTasksState.assignees = [];
        projectModalYouTrackTasksState.stateOptions = [];
        projectModalYouTrackTasksState.customFieldColumns = [];
        projectModalYouTrackTasksState.pendingTasks = [];
        resetProjectModalYouTrackTaskDraftState();
        projectModalYouTrackTasksState.editingTaskId = "";
        projectModalYouTrackTasksState.editingField = "";
        projectModalYouTrackTasksState.editingValue = "";
        projectModalYouTrackTasksState.editingSubmitting = false;
        projectModalYouTrackTasksState.editingError = "";
        dom.projectModal.dataset.youtrackProjectKey = projectKey;
        dom.projectModalYouTrackTasks.hidden = false;
        if (dom.projectModalYouTrackTasksCount) {
            dom.projectModalYouTrackTasksCount.textContent = projectKey;
        }
        dom.projectModalYouTrackTasksBody.innerHTML = `<div class="project-modal-youtrack-tasks-message is-loading">Chargement des tâches YouTrack...</div>`;

        try {
            const payload = await loadYouTrackProjectTasks(projectKey);
            if (requestToken !== projectModalTasksRequestToken) {
                return;
            }

            renderProjectModalYouTrackTasks(
                payload?.tasks || [],
                projectKey,
                payload?.assignees || [],
                payload?.stateOptions || [],
                payload?.customFieldColumns || []
            );
        } catch (error) {
            if (requestToken !== projectModalTasksRequestToken) {
                return;
            }

            dom.projectModalYouTrackTasks.hidden = false;
            if (dom.projectModalYouTrackTasksCount) {
                dom.projectModalYouTrackTasksCount.textContent = projectKey;
            }
            dom.projectModalYouTrackTasksBody.innerHTML = `
                <div class="project-modal-youtrack-tasks-message is-error">
                    ${escapeHtml(error.message || "Impossible de charger les tâches YouTrack.")}
                </div>
            `;
        }

        return;
    }

    if (isProjectModalYouTrackRequested(project)) {
        projectModalTasksRequestToken += 1;
        projectModalYouTrackTasksState.mode = "pending";
        projectModalYouTrackTasksState.projectKey = "";
        projectModalYouTrackTasksState.tasks = [];
        projectModalYouTrackTasksState.assignees = [];
        projectModalYouTrackTasksState.stateOptions = [];
        projectModalYouTrackTasksState.customFieldColumns = [];
        resetProjectModalYouTrackTaskDraftState();
        projectModalYouTrackTasksState.editingTaskId = "";
        projectModalYouTrackTasksState.editingField = "";
        projectModalYouTrackTasksState.editingValue = "";
        projectModalYouTrackTasksState.editingSubmitting = false;
        dom.projectModalYouTrackTasks.hidden = false;
        delete dom.projectModal.dataset.youtrackProjectKey;
        renderProjectModalPendingYouTrackTasks();
        return;
    }

    if (projectModalYouTrackTasksState.mode === "pending" || projectModalYouTrackTasksState.mode === "remote") {
        resetProjectModalYouTrackTasks();
    }
}

function getVisibleProjectTaskColumns() {
    return getProjectModalTaskColumns();
}

function getProjectModalAssigneeChoices() {
    const teamMembers = getProjectModalTeamMembers().map((member) => ({
        id: member.youtrackId || (getAvailableProjectTeamUsers().find((user) => user.id === member.id)?.youtrackId || member.id),
        ringId: member.id,
        label: member.displayName || member.login || member.id,
    }));

    if (teamMembers.length) {
        return teamMembers;
    }

    return projectModalYouTrackTasksState.assignees.map((assignee) => ({
        id: String(assignee.id || "").trim(),
        label: String(assignee.name || assignee.login || assignee.id || "").trim(),
    })).filter((assignee) => assignee.id !== "");
}

function syncProjectModalYouTrackTaskPermissions() {
    const canEdit = canCurrentUserEditProjectTaskTable();

    if (!canEdit) {
        projectModalYouTrackTasksState.draftActive = false;
        projectModalYouTrackTasksState.draftSubmitting = false;
        projectModalYouTrackTasksState.draftError = "";
        projectModalYouTrackTasksState.editingTaskId = "";
        projectModalYouTrackTasksState.editingField = "";
        projectModalYouTrackTasksState.editingValue = "";
        projectModalYouTrackTasksState.editingSubmitting = false;
        projectModalYouTrackTasksState.editingError = "";
    }

    if (dom.projectModalYouTrackTaskToggle) {
        dom.projectModalYouTrackTaskToggle.disabled = !canEdit;
        dom.projectModalYouTrackTaskToggle.title = canEdit
            ? ""
            : "Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.";
    }

    dom.projectModalYouTrackTasks?.classList.toggle("is-readonly", !canEdit);
    return canEdit;
}

function syncProjectModalTaskAreaLayout(taskCount = 0) {
    const shouldExpand = taskCount > 0 || projectModalYouTrackTasksState.draftActive;
    dom.projectModal?.classList.toggle("has-wide-tasks", shouldExpand);
}

function formatProjectTaskDisplayDate(value) {
    const normalized = String(value || "").trim();
    if (!normalized) {
        return "-";
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        const [year, month, day] = normalized.split("-");
        return `${day}/${month}/${year}`;
    }

    if (/^\d{13}$/.test(normalized) || /^\d{10}$/.test(normalized)) {
        const timestamp = normalized.length === 13 ? Number(normalized) : Number(normalized) * 1000;
        const date = new Date(timestamp);
        if (!Number.isNaN(date.getTime())) {
            return [
                String(date.getDate()).padStart(2, "0"),
                String(date.getMonth() + 1).padStart(2, "0"),
                String(date.getFullYear())
            ].join("/");
        }
    }

    return normalized;
}

function getHiddenProjectTaskColumns() {
    const visibleColumns = new Set(getVisibleProjectTaskColumns());
    return getProjectTaskColumnOptions().filter((column) => !visibleColumns.has(column.key));
}

function getProjectModalStateOptions() {
    return Array.isArray(projectModalYouTrackTasksState.stateOptions)
        ? projectModalYouTrackTasksState.stateOptions
        : [];
}

function resetProjectModalYouTrackTaskDraftState() {
    projectModalYouTrackTasksState.draftActive = false;
    projectModalYouTrackTasksState.draftSummary = "";
    projectModalYouTrackTasksState.draftAssigneeId = "";
    projectModalYouTrackTasksState.draftDueDate = "";
    projectModalYouTrackTasksState.draftState = "";
    projectModalYouTrackTasksState.draftCustomFieldValues = {};
    projectModalYouTrackTasksState.draftSubmitting = false;
    projectModalYouTrackTasksState.draftError = "";
}

function getProjectModalDraftCustomFieldValue(columnKey) {
    const normalizedColumnKey = String(columnKey || "").trim();
    if (!normalizedColumnKey) {
        return "";
    }

    return String(projectModalYouTrackTasksState.draftCustomFieldValues?.[normalizedColumnKey] || "");
}

function setProjectModalDraftCustomFieldValue(columnKey, value) {
    const normalizedColumnKey = String(columnKey || "").trim();
    if (!normalizedColumnKey) {
        return;
    }

    projectModalYouTrackTasksState.draftCustomFieldValues = {
        ...(projectModalYouTrackTasksState.draftCustomFieldValues || {}),
        [normalizedColumnKey]: String(value || "")
    };
}

function findProjectModalYouTrackTask(taskId) {
    return projectModalYouTrackTasksState.tasks.find((task) => String(task.idReadable || "").trim() === String(taskId || "").trim()) || null;
}

function isProjectModalTaskEditing(taskId, field) {
    return (
        String(projectModalYouTrackTasksState.editingTaskId || "") === String(taskId || "")
        && String(projectModalYouTrackTasksState.editingField || "") === String(field || "")
    );
}

function startProjectModalYouTrackTaskEdit(taskId, field) {
    const task = findProjectModalYouTrackTask(taskId);
    if (!task || projectModalYouTrackTasksState.editingSubmitting) {
        return;
    }

    if (projectModalYouTrackTasksState.draftActive) {
        closeProjectModalYouTrackTaskDraft();
    }

    let editingValue = "";
    if (field === "summary") {
        editingValue = String(task.summary || "");
    } else if (field === "assignee") {
        editingValue = String(task.assigneeId || "");
    } else if (field === "dueDate") {
        editingValue = String(task.dueDateInput || "");
    } else if (field === "state") {
        editingValue = String(task.state || "");
    } else if (isDynamicProjectTaskColumnKey(field)) {
        editingValue = String(task?.customFieldInputValues?.[field] ?? task?.customFieldValues?.[field] ?? "");
    }

    projectModalYouTrackTasksState.editingTaskId = String(taskId || "");
    projectModalYouTrackTasksState.editingField = String(field || "");
    projectModalYouTrackTasksState.editingValue = editingValue;
    projectModalYouTrackTasksState.editingError = "";
    rerenderProjectModalYouTrackTasks();
}

function cancelProjectModalYouTrackTaskEdit() {
    projectModalYouTrackTasksState.editingTaskId = "";
    projectModalYouTrackTasksState.editingField = "";
    projectModalYouTrackTasksState.editingValue = "";
    projectModalYouTrackTasksState.editingSubmitting = false;
    projectModalYouTrackTasksState.editingError = "";
    rerenderProjectModalYouTrackTasks();
}

function updateProjectModalYouTrackTaskInMemory(nextTask) {
    const taskId = String(nextTask?.idReadable || "").trim();
    if (!taskId) {
        return;
    }

    const taskIndex = projectModalYouTrackTasksState.tasks.findIndex((task) => String(task.idReadable || "").trim() === taskId);
    if (taskIndex >= 0) {
        projectModalYouTrackTasksState.tasks.splice(taskIndex, 1, {
            ...projectModalYouTrackTasksState.tasks[taskIndex],
            ...nextTask,
        });
    }
}

function renderProjectModalTaskTableHeader() {
    const visibleColumns = getVisibleProjectTaskColumns();
    const hiddenColumns = getHiddenProjectTaskColumns();

    const headers = visibleColumns.map((columnKey) => {
        const column = getProjectTaskColumnOption(columnKey);
        if (!column) {
            return "";
        }

        const canRemove = columnKey !== "summary";
        return `
            <th class="project-modal-youtrack-header-cell project-modal-youtrack-col-${escapeHtml(columnKey)}">
                <span>${escapeHtml(column.label)}</span>
                ${canRemove ? `
                    <button
                        class="project-modal-youtrack-column-remove"
                        type="button"
                        data-task-column-remove="${escapeHtml(columnKey)}"
                        aria-label="Masquer la colonne ${escapeHtml(column.label)}"
                        title="Masquer la colonne"
                    >
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                ` : ""}
            </th>
        `;
    });

    headers.push(`
        <th class="project-modal-youtrack-action-head">
            ${hiddenColumns.length ? `
                <details class="project-modal-youtrack-column-adder">
                    <summary class="project-modal-youtrack-column-add-button" aria-label="Ajouter une colonne" title="Ajouter une colonne">+</summary>
                    <div class="project-modal-youtrack-column-adder-menu">
                        ${hiddenColumns.map((column) => `
                            <button type="button" data-task-column-add="${escapeHtml(column.key)}">${escapeHtml(column.label)}</button>
                        `).join("")}
                    </div>
                </details>
            ` : ""}
        </th>
    `);

    return headers.join("");
}

function renderProjectModalEditableTaskValue(taskId, field, label, content, className = "", columnKey = field) {
    return `
        <td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-${escapeHtml(columnKey)}">
            <button
                class="project-modal-youtrack-cell-trigger${className ? ` ${className}` : ""}"
                type="button"
                data-task-cell="${escapeHtml(taskId)}"
                data-task-field="${escapeHtml(field)}"
            >
                ${content}
            </button>
        </td>
    `;
}

function renderProjectModalReadonlyTaskValue(label, content, className = "", columnKey = "") {
    const resolvedColumnKey = columnKey || "readonly";
    return `
        <td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-${escapeHtml(resolvedColumnKey)}">
            <div class="project-modal-youtrack-readonly-value${className ? ` ${className}` : ""}">
                ${content}
            </div>
        </td>
    `;
}

function getProjectModalLiveTaskFieldValue(task, field) {
    const taskId = String(task?.idReadable || "").trim();
    if (
        taskId !== ""
        && String(projectModalYouTrackTasksState.editingTaskId || "") === taskId
        && String(projectModalYouTrackTasksState.editingField || "") === String(field || "")
    ) {
        return String(projectModalYouTrackTasksState.editingValue || "");
    }

    if (field === "summary") {
        return String(task?.summary || "");
    }

    if (field === "assignee") {
        return String(task?.assigneeId || "");
    }

    if (field === "dueDate") {
        return String(task?.dueDateInput || "");
    }

    if (field === "state") {
        return String(task?.state || "");
    }

    if (isDynamicProjectTaskColumnKey(field)) {
        return String(task?.customFieldInputValues?.[field] ?? task?.customFieldValues?.[field] ?? "");
    }

    return "";
}

function syncProjectModalTaskEditStateFromElement(editElement) {
    if (!(editElement instanceof HTMLElement)) {
        return;
    }

    projectModalYouTrackTasksState.editingTaskId = String(editElement.dataset.youtrackTaskId || "").trim();
    projectModalYouTrackTasksState.editingField = String(editElement.dataset.youtrackTaskField || "").trim();
    projectModalYouTrackTasksState.editingValue = "value" in editElement ? String(editElement.value || "") : "";
}

function renderProjectModalDynamicTaskFieldEditor(taskId, column, value) {
    const columnKey = String(column?.key || "").trim();
    const label = String(column?.label || columnKey).trim() || columnKey;
    const inputKind = String(column?.inputKind || "").trim();
    const placeholder = escapeHtml(String(column?.emptyFieldText || "-"));
    const disabled = projectModalYouTrackTasksState.editingSubmitting ? "disabled" : "";

    if (inputKind === "select") {
        const availableOptions = Array.isArray(column?.options) ? [...column.options] : [];
        if (value && !availableOptions.some((option) => String(option?.name || "").trim() === value)) {
            availableOptions.push({
                id: "",
                name: value,
                presentation: value,
            });
        }

        const options = [
            `<option value="">-</option>`,
            ...availableOptions.map((option) => {
                const optionValue = String(option?.name || "").trim();
                const optionLabel = String(option?.presentation || option?.name || optionValue).trim();
                return `
                    <option value="${escapeHtml(optionValue)}"${optionValue === value ? " selected" : ""}>
                        ${escapeHtml(optionLabel || optionValue)}
                    </option>
                `;
            }),
        ].join("");

        return `
            <td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-custom">
                <select
                    class="project-modal-youtrack-inline-select"
                    data-youtrack-task-edit-input
                    data-youtrack-task-id="${escapeHtml(taskId)}"
                    data-youtrack-task-field="${escapeHtml(columnKey)}"
                    ${disabled}
                >
                    ${options}
                </select>
            </td>
        `;
    }

    return `
        <td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-custom">
            <input
                class="project-modal-youtrack-inline-input"
                data-youtrack-task-edit-input
                data-youtrack-task-id="${escapeHtml(taskId)}"
                data-youtrack-task-field="${escapeHtml(columnKey)}"
                type="text"
                value="${escapeHtml(value)}"
                placeholder="${placeholder}"
                autocomplete="off"
                ${disabled}
            >
        </td>
    `;
}

function renderProjectModalYouTrackTaskRow(task) {
    const visibleColumns = getVisibleProjectTaskColumns();
    const canEdit = canCurrentUserEditProjectTaskTable();
    const state = String(task.state || "-");
    const stateClassName = getYouTrackTaskStateClassName(state);
    const taskId = String(task.idReadable || "").trim();
    const identifier = escapeHtml(taskId);
    const summary = escapeHtml(String(task.summary || ""));
    const assignee = escapeHtml(String(task.assignee || "-"));
    const dueDate = escapeHtml(formatProjectTaskDisplayDate(task.dueDate));
    const stateLabel = escapeHtml(state);
    const url = escapeHtml(buildLocalTicketDetailUrl(taskId));
    const summaryValue = escapeHtml(getProjectModalLiveTaskFieldValue(task, "summary"));
    const assigneeValue = String(getProjectModalLiveTaskFieldValue(task, "assignee"));
    const dueDateValue = escapeHtml(getProjectModalLiveTaskFieldValue(task, "dueDate"));
    const stateValue = String(getProjectModalLiveTaskFieldValue(task, "state"));
    const assigneeOptions = [
        `<option value="">-</option>`,
        ...getProjectModalAssigneeChoices().map((assigneeOption) => `
            <option value="${escapeHtml(String(assigneeOption.id || ""))}"${String(assigneeOption.id || "") === assigneeValue ? " selected" : ""}>
                ${escapeHtml(String(assigneeOption.label || assigneeOption.id || ""))}
            </option>
        `),
    ].join("");
    const availableStateOptions = [...getProjectModalStateOptions()];
    if (state && !availableStateOptions.some((stateOption) => String(stateOption.name || "") === state)) {
        availableStateOptions.push({ name: state });
    }
    const stateOptions = [
        `<option value="">-</option>`,
        ...availableStateOptions.map((stateOption) => `
            <option value="${escapeHtml(String(stateOption.name || ""))}"${String(stateOption.name || "") === stateValue ? " selected" : ""}>
                ${escapeHtml(String(stateOption.name || ""))}
            </option>
        `),
    ].join("");

    const cells = visibleColumns.map((columnKey) => {
        if (columnKey === "idReadable") {
            return `
                <td data-label="ID" class="project-modal-youtrack-col-idReadable">
                    ${identifier ? `<a href="${url}" target="_blank" rel="noreferrer">${identifier}</a>` : "-"}
                </td>
            `;
        }

        if (columnKey === "summary") {
            if (!canEdit) {
                return renderProjectModalReadonlyTaskValue("Résumé", summary || "-", "", "summary");
            }

            if (isProjectModalTaskEditing(taskId, "summary")) {
                return `
                    <td data-label="Résumé" class="project-modal-youtrack-col-summary">
                        <input
                            class="project-modal-youtrack-inline-input"
                            data-youtrack-task-edit-input
                            data-youtrack-task-id="${escapeHtml(taskId)}"
                            data-youtrack-task-field="summary"
                            type="text"
                            value="${summaryValue}"
                            placeholder="-"
                            autocomplete="off"
                            ${projectModalYouTrackTasksState.editingSubmitting ? "disabled" : ""}
                        >
                    </td>
                `;
            }

            return renderProjectModalEditableTaskValue(taskId, "summary", "Résumé", summary || "-", "", "summary");
        }

        if (columnKey === "assignee") {
            if (!canEdit) {
                return renderProjectModalReadonlyTaskValue("Responsable", assignee, "", "assignee");
            }

            if (isProjectModalTaskEditing(taskId, "assignee")) {
                return `
                    <td data-label="Responsable" class="project-modal-youtrack-col-assignee">
                        <select
                            class="project-modal-youtrack-inline-select"
                            data-youtrack-task-edit-input
                            data-youtrack-task-id="${escapeHtml(taskId)}"
                            data-youtrack-task-field="assignee"
                            ${projectModalYouTrackTasksState.editingSubmitting ? "disabled" : ""}
                        >
                            ${assigneeOptions}
                        </select>
                    </td>
                `;
            }

            return renderProjectModalEditableTaskValue(taskId, "assignee", "Responsable", assignee, "", "assignee");
        }

        if (columnKey === "dueDate") {
            if (!canEdit) {
                return renderProjectModalReadonlyTaskValue("Date échéance", dueDate, "", "dueDate");
            }

            if (isProjectModalTaskEditing(taskId, "dueDate")) {
                return `
                    <td data-label="Date échéance" class="project-modal-youtrack-col-dueDate">
                        <input
                            class="project-modal-youtrack-inline-date"
                            data-youtrack-task-edit-input
                            data-youtrack-task-id="${escapeHtml(taskId)}"
                            data-youtrack-task-field="dueDate"
                            type="date"
                            value="${dueDateValue}"
                            ${projectModalYouTrackTasksState.editingSubmitting ? "disabled" : ""}
                        >
                    </td>
                `;
            }

            return renderProjectModalEditableTaskValue(taskId, "dueDate", "Date échéance", dueDate, "", "dueDate");
        }

        if (columnKey === "state") {
            if (!canEdit) {
                return renderProjectModalReadonlyTaskValue(
                    "État",
                    `<span class="project-status-badge ${escapeHtml(stateClassName)}">${stateLabel}</span>`,
                    "is-badge",
                    "state"
                );
            }

            if (isProjectModalTaskEditing(taskId, "state")) {
                return `
                    <td data-label="État" class="project-modal-youtrack-col-state">
                        <select
                            class="project-modal-youtrack-inline-select"
                            data-youtrack-task-edit-input
                            data-youtrack-task-id="${escapeHtml(taskId)}"
                            data-youtrack-task-field="state"
                            ${projectModalYouTrackTasksState.editingSubmitting ? "disabled" : ""}
                        >
                            ${stateOptions}
                        </select>
                    </td>
                `;
            }

            return renderProjectModalEditableTaskValue(
                taskId,
                "state",
                "État",
                `<span class="project-status-badge ${escapeHtml(stateClassName)}">${stateLabel}</span>`,
                "is-badge",
                "state"
            );
        }

        if (isDynamicProjectTaskColumnKey(columnKey)) {
            const column = getProjectTaskColumnOption(columnKey);
            const label = String(column?.label || columnKey.replace(/^cf__/, "") || "Champ").trim();
            const value = String(task?.customFieldValues?.[columnKey] || "-").trim() || "-";
            if (!canEdit) {
                return renderProjectModalReadonlyTaskValue(label, escapeHtml(value), "", "custom");
            }

            if (isProjectModalTaskEditing(taskId, columnKey)) {
                return renderProjectModalDynamicTaskFieldEditor(
                    taskId,
                    column,
                    getProjectModalLiveTaskFieldValue(task, columnKey)
                );
            }

            return renderProjectModalEditableTaskValue(
                taskId,
                columnKey,
                label,
                escapeHtml(value),
                "",
                "custom"
            );
        }

        return "";
    });

    cells.push(`
        <td data-label="Action" class="project-modal-youtrack-action-cell">
            ${taskId && canEdit ? `
                <button
                    class="project-modal-youtrack-delete-button"
                    type="button"
                    data-youtrack-task-delete="${escapeHtml(taskId)}"
                    aria-label="Supprimer la tâche ${escapeHtml(taskId)}"
                    title="Supprimer la tâche"
                >
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
            ` : ""}
        </td>
    `);

    return `<tr>${cells.join("")}</tr>`;
}

function renderProjectModalPendingTaskRow(task, index) {
    const visibleColumns = getVisibleProjectTaskColumns();
    const canEdit = canCurrentUserEditProjectTaskTable();
    const cells = visibleColumns.map((columnKey) => {
        if (columnKey === "idReadable") {
            return `<td data-label="ID" class="project-modal-youtrack-col-idReadable"><span class="project-modal-youtrack-auto-id">Auto</span></td>`;
        }

        if (columnKey === "summary") {
            return `<td data-label="Résumé" class="project-modal-youtrack-col-summary">${escapeHtml(String(task.summary || "")) || "-"}</td>`;
        }

        if (columnKey === "assignee") {
            return `<td data-label="Responsable" class="project-modal-youtrack-col-assignee">${escapeHtml(String(task.assigneeName || "-"))}</td>`;
        }

        if (columnKey === "dueDate") {
            return `<td data-label="Date échéance" class="project-modal-youtrack-col-dueDate">${escapeHtml(formatProjectTaskDisplayDate(task.dueDate))}</td>`;
        }

        if (columnKey === "state") {
            const stateLabel = String(task.state || "").trim() || "À créer";
            const stateClassName = getYouTrackTaskStateClassName(stateLabel);
            return `
                <td data-label="État" class="project-modal-youtrack-col-state">
                    <span class="project-status-badge ${escapeHtml(stateClassName)}">${escapeHtml(stateLabel)}</span>
                </td>
            `;
        }

        if (isDynamicProjectTaskColumnKey(columnKey)) {
            const column = getProjectTaskColumnOption(columnKey);
            const label = String(column?.label || columnKey.replace(/^cf__/, "") || "Champ").trim();
            const value = String(
                task?.customFieldValues?.[columnKey]
                ?? task?.customFieldInputValues?.[columnKey]
                ?? "-"
            ).trim() || "-";
            return `<td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-custom">${escapeHtml(value)}</td>`;
        }

        return "";
    });

    cells.push(`
        <td data-label="Action" class="project-modal-youtrack-action-cell">
            ${canEdit ? `<button
                class="project-modal-youtrack-delete-button"
                type="button"
                data-youtrack-pending-task-delete="${index}"
                aria-label="Retirer la tâche en attente"
                title="Retirer la tâche en attente"
            >
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>` : ""}
        </td>
    `);

    return `<tr>${cells.join("")}</tr>`;
}

function renderProjectModalDraftDynamicTaskField(column) {
    const columnKey = String(column?.key || "").trim();
    const label = String(column?.label || columnKey.replace(/^cf__/, "") || "Champ").trim();
    const inputKind = String(column?.inputKind || "").trim();
    const currentValue = getProjectModalDraftCustomFieldValue(columnKey);
    const disabled = projectModalYouTrackTasksState.draftSubmitting ? "disabled" : "";
    const placeholder = escapeHtml(String(column?.emptyFieldText || "-"));

    if (inputKind === "select") {
        const availableOptions = Array.isArray(column?.options) ? [...column.options] : [];
        if (currentValue && !availableOptions.some((option) => String(option?.name || "").trim() === currentValue)) {
            availableOptions.push({
                id: "",
                name: currentValue,
                presentation: currentValue,
            });
        }

        const options = [
            `<option value="">-</option>`,
            ...availableOptions.map((option) => {
                const optionValue = String(option?.name || "").trim();
                const optionLabel = String(option?.presentation || option?.name || optionValue).trim();
                return `
                    <option value="${escapeHtml(optionValue)}"${optionValue === currentValue ? " selected" : ""}>
                        ${escapeHtml(optionLabel || optionValue)}
                    </option>
                `;
            }),
        ].join("");

        return `
            <td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-custom">
                <select
                    class="project-modal-youtrack-inline-select"
                    data-youtrack-task-draft-custom-field="${escapeHtml(columnKey)}"
                    ${disabled}
                >
                    ${options}
                </select>
            </td>
        `;
    }

    return `
        <td data-label="${escapeHtml(label)}" class="project-modal-youtrack-col-custom">
            <input
                class="project-modal-youtrack-inline-input"
                data-youtrack-task-draft-custom-field="${escapeHtml(columnKey)}"
                type="text"
                value="${escapeHtml(currentValue)}"
                placeholder="${placeholder}"
                autocomplete="off"
                ${disabled}
            >
        </td>
    `;
}

function renderProjectModalTaskTable(rowsMarkup, emptyMessage, taskCount) {
    dom.projectModalYouTrackTasksBody.innerHTML = `
        <div class="project-modal-youtrack-table-wrap">
            <table class="project-modal-youtrack-table">
                <thead>
                    <tr>${renderProjectModalTaskTableHeader()}</tr>
                </thead>
                <tbody>
                    ${projectModalYouTrackTasksState.draftActive ? renderProjectModalYouTrackDraftRow() : ""}
                    ${rowsMarkup || (projectModalYouTrackTasksState.draftActive ? "" : `
                        <tr>
                            <td colspan="${getVisibleProjectTaskColumns().length + 1}">
                                <div class="project-modal-youtrack-tasks-message">
                                    ${emptyMessage}
                                </div>
                            </td>
                        </tr>
                    `)}
                </tbody>
            </table>
        </div>
    `;

    syncProjectModalTaskAreaLayout(taskCount);

    if (projectModalYouTrackTasksState.draftActive) {
        requestAnimationFrame(() => {
            dom.projectModalYouTrackTasksBody
                ?.querySelector("[data-youtrack-task-draft-input]")
                ?.focus();
        });
        return;
    }

    if (projectModalYouTrackTasksState.editingTaskId && projectModalYouTrackTasksState.editingField) {
        requestAnimationFrame(() => {
            const selector = `[data-youtrack-task-edit-input][data-youtrack-task-id="${cssEscape(projectModalYouTrackTasksState.editingTaskId)}"][data-youtrack-task-field="${cssEscape(projectModalYouTrackTasksState.editingField)}"]`;
            const input = dom.projectModalYouTrackTasksBody?.querySelector(selector);
            if (input instanceof HTMLElement) {
                input.focus();
                if (input instanceof HTMLInputElement && input.type === "text") {
                    input.select();
                }
            }
        });
    }
}

function renderProjectModalYouTrackTasks(
    tasks,
    projectKey,
    assignees = projectModalYouTrackTasksState.assignees,
    stateOptions = projectModalYouTrackTasksState.stateOptions,
    customFieldColumns = projectModalYouTrackTasksState.customFieldColumns
) {
    projectModalYouTrackTasksState.mode = "remote";
    projectModalYouTrackTasksState.projectKey = projectKey;
    projectModalYouTrackTasksState.tasks = Array.isArray(tasks) ? tasks : [];
    projectModalYouTrackTasksState.assignees = Array.isArray(assignees) ? assignees : [];
    projectModalYouTrackTasksState.stateOptions = Array.isArray(stateOptions) ? stateOptions : [];
    setProjectModalCustomFieldColumns(customFieldColumns);
    const canEdit = syncProjectModalYouTrackTaskPermissions();
    dom.projectModalYouTrackTasks.hidden = false;
    if (dom.projectModalYouTrackTasksCount) {
        dom.projectModalYouTrackTasksCount.textContent = `${tasks.length} tâche(s) - ${projectKey}${canEdit ? "" : " · lecture seule"}`;
    }

    renderProjectModalTaskTable(
        tasks.map((task) => renderProjectModalYouTrackTaskRow(task)).join(""),
        "Aucune tâche trouvée pour ce projet YouTrack.",
        tasks.length
    );
}

function renderProjectModalPendingYouTrackTasks() {
    projectModalYouTrackTasksState.mode = "pending";
    const canEdit = syncProjectModalYouTrackTaskPermissions();
    dom.projectModalYouTrackTasks.hidden = false;
    if (dom.projectModalYouTrackTasksCount) {
        dom.projectModalYouTrackTasksCount.textContent = `${projectModalYouTrackTasksState.pendingTasks.length} tâche(s) en attente${canEdit ? "" : " · lecture seule"}`;
    }

    renderProjectModalTaskTable(
        projectModalYouTrackTasksState.pendingTasks.map((task, index) => renderProjectModalPendingTaskRow(task, index)).join(""),
        "Ajoutez des tâches. Elles seront créées dans YouTrack à la validation du projet.",
        projectModalYouTrackTasksState.pendingTasks.length
    );
}

function rerenderProjectModalYouTrackTasks() {
    if (projectModalYouTrackTasksState.mode === "pending") {
        renderProjectModalPendingYouTrackTasks();
        return;
    }

    if (projectModalYouTrackTasksState.mode === "remote") {
        renderProjectModalYouTrackTasks(
            projectModalYouTrackTasksState.tasks,
            projectModalYouTrackTasksState.projectKey,
            projectModalYouTrackTasksState.assignees,
            projectModalYouTrackTasksState.stateOptions,
            projectModalYouTrackTasksState.customFieldColumns
        );
        return;
    }

    resetProjectModalYouTrackTasks();
}

function renderProjectModalYouTrackDraftRow() {
    const isPendingMode = projectModalYouTrackTasksState.mode === "pending";
    const summary = escapeHtml(projectModalYouTrackTasksState.draftSummary);
    const dueDate = escapeHtml(String(projectModalYouTrackTasksState.draftDueDate || ""));
    const draftStateValue = String(projectModalYouTrackTasksState.draftState || "").trim();
    const errorMarkup = projectModalYouTrackTasksState.draftError
        ? `<div class="project-modal-youtrack-inline-error">${escapeHtml(projectModalYouTrackTasksState.draftError)}</div>`
        : "";
    const assigneeOptions = [
        `<option value="">-</option>`,
        ...getProjectModalAssigneeChoices().map((assignee) => `
            <option value="${escapeHtml(String(assignee.id || ""))}"${String(assignee.id || "") === projectModalYouTrackTasksState.draftAssigneeId ? " selected" : ""}>
                ${escapeHtml(String(assignee.label || assignee.id || ""))}
            </option>
        `),
    ].join("");
    const availableStateOptions = [...getProjectModalStateOptions()];
    if (draftStateValue && !availableStateOptions.some((stateOption) => String(stateOption.name || "").trim() === draftStateValue)) {
        availableStateOptions.push({ name: draftStateValue });
    }
    const stateOptions = [
        `<option value="">-</option>`,
        ...availableStateOptions.map((stateOption) => {
            const optionValue = String(stateOption?.name || "").trim();
            return `
                <option value="${escapeHtml(optionValue)}"${optionValue === draftStateValue ? " selected" : ""}>
                    ${escapeHtml(optionValue || "-")}
                </option>
            `;
        }),
    ].join("");

    const cells = getVisibleProjectTaskColumns().map((columnKey) => {
        if (columnKey === "idReadable") {
            return `<td data-label="ID" class="project-modal-youtrack-col-idReadable"><span class="project-modal-youtrack-auto-id">Auto</span></td>`;
        }

        if (columnKey === "summary") {
            return `
                <td data-label="Résumé" class="project-modal-youtrack-col-summary">
                    <input
                        class="project-modal-youtrack-inline-input"
                        data-youtrack-task-draft-input
                        type="text"
                        value="${summary}"
                        placeholder="Saisir le résumé de la tâche..."
                        autocomplete="off"
                        ${projectModalYouTrackTasksState.draftSubmitting ? "disabled" : ""}
                    >
                    ${errorMarkup}
                </td>
            `;
        }

        if (columnKey === "assignee") {
            return `
                <td data-label="Responsable" class="project-modal-youtrack-col-assignee">
                    <select class="project-modal-youtrack-inline-select" data-youtrack-task-draft-assignee ${projectModalYouTrackTasksState.draftSubmitting ? "disabled" : ""}>
                        ${assigneeOptions}
                    </select>
                </td>
            `;
        }

        if (columnKey === "dueDate") {
            return `
                <td data-label="Date échéance" class="project-modal-youtrack-col-dueDate">
                    <input
                        class="project-modal-youtrack-inline-date"
                        data-youtrack-task-draft-due-date
                        type="date"
                        value="${dueDate}"
                        ${projectModalYouTrackTasksState.draftSubmitting ? "disabled" : ""}
                    >
                </td>
            `;
        }

        if (columnKey === "state") {
            return `
                <td data-label="État" class="project-modal-youtrack-col-state">
                    <select class="project-modal-youtrack-inline-select" data-youtrack-task-draft-state ${projectModalYouTrackTasksState.draftSubmitting ? "disabled" : ""}>
                        ${stateOptions}
                    </select>
                </td>
            `;
        }

        if (isDynamicProjectTaskColumnKey(columnKey)) {
            const column = getProjectTaskColumnOption(columnKey);
            return renderProjectModalDraftDynamicTaskField(column || { key: columnKey, label: columnKey });
        }

        return "";
    });

    cells.push(`<td data-label="Action"></td>`);
    return `<tr class="project-modal-youtrack-draft-row">${cells.join("")}</tr>`;
}

function getYouTrackTaskStateClassName(state) {
    const normalizedState = String(state || "").trim().toLowerCase();

    if (!normalizedState) {
        return "is-to-plan";
    }

    if (
        normalizedState.includes("done") ||
        normalizedState.includes("termin") ||
        normalizedState.includes("résolu") ||
        normalizedState.includes("resolu") ||
        normalizedState.includes("close") ||
        normalizedState.includes("clos") ||
        normalizedState.includes("fixed")
    ) {
        return "is-done";
    }

    if (
        normalizedState.includes("progress") ||
        normalizedState.includes("cours") ||
        normalizedState.includes("doing") ||
        normalizedState.includes("work")
    ) {
        return "is-in-progress";
    }

    if (
        normalizedState.includes("standby") ||
        normalizedState.includes("wait") ||
        normalizedState.includes("hold") ||
        normalizedState.includes("block")
    ) {
        return "is-standby";
    }

    if (
        normalizedState.includes("plan") ||
        normalizedState.includes("ready") ||
        normalizedState.includes("review")
    ) {
        return "is-planned";
    }

    return "is-to-plan";
}

function buildLocalTicketDetailUrl(taskId) {
    const normalizedTaskId = String(taskId || "").trim();
    return GANTT_TICKET_DETAIL_URL_PATTERN.replace("__ID__", encodeURIComponent(normalizedTaskId));
}

function openProjectModalYouTrackTaskDraft() {
    if (dom.projectModalYouTrackTasks?.hidden || projectModalYouTrackTasksState.draftActive) {
        return;
    }

    if (!canCurrentUserEditProjectTaskTable()) {
        showProjectModalError("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
        return;
    }

    projectModalYouTrackTasksState.editingTaskId = "";
    projectModalYouTrackTasksState.editingField = "";
    projectModalYouTrackTasksState.editingValue = "";
    projectModalYouTrackTasksState.editingSubmitting = false;
    projectModalYouTrackTasksState.editingError = "";
    resetProjectModalYouTrackTaskDraftState();
    projectModalYouTrackTasksState.draftActive = true;

    if (dom.projectModalYouTrackTaskToggle) {
        dom.projectModalYouTrackTaskToggle.disabled = true;
    }

    rerenderProjectModalYouTrackTasks();
}

function closeProjectModalYouTrackTaskDraft() {
    resetProjectModalYouTrackTaskDraftState();

    if (dom.projectModalYouTrackTaskToggle) {
        dom.projectModalYouTrackTaskToggle.disabled = false;
    }

    rerenderProjectModalYouTrackTasks();
}

function onProjectModalYouTrackTasksBodyClick(event) {
    const removeColumnButton = event.target.closest("[data-task-column-remove]");
    if (removeColumnButton) {
        const columnKey = String(removeColumnButton.dataset.taskColumnRemove || "").trim();
        if (columnKey) {
            setProjectModalTaskColumns(getProjectModalTaskColumns().filter((column) => column !== columnKey));
            rerenderProjectModalYouTrackTasks();
        }
        return;
    }

    const addColumnButton = event.target.closest("[data-task-column-add]");
    if (addColumnButton) {
        const columnKey = String(addColumnButton.dataset.taskColumnAdd || "").trim();
        if (columnKey) {
            setProjectModalTaskColumns([...getProjectModalTaskColumns(), columnKey]);
            addColumnButton.closest(".project-modal-youtrack-column-adder")?.removeAttribute("open");
            rerenderProjectModalYouTrackTasks();
        }
        return;
    }

    const editableCell = event.target.closest("[data-task-cell][data-task-field]");
    if (editableCell) {
        if (!canCurrentUserEditProjectTaskTable()) {
            showProjectModalError("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
            return;
        }
        startProjectModalYouTrackTaskEdit(
            editableCell.dataset.taskCell || "",
            editableCell.dataset.taskField || ""
        );
        return;
    }

    const pendingDeleteButton = event.target.closest("[data-youtrack-pending-task-delete]");
    if (pendingDeleteButton) {
        if (!canCurrentUserEditProjectTaskTable()) {
            showProjectModalError("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
            return;
        }
        const pendingIndex = Number(pendingDeleteButton.dataset.youtrackPendingTaskDelete);
        if (Number.isInteger(pendingIndex) && pendingIndex >= 0) {
            projectModalYouTrackTasksState.pendingTasks.splice(pendingIndex, 1);
            rerenderProjectModalYouTrackTasks();
        }
        return;
    }

    const deleteButton = event.target.closest("[data-youtrack-task-delete]");
    if (deleteButton) {
        if (!canCurrentUserEditProjectTaskTable()) {
            showProjectModalError("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
            return;
        }
        deleteProjectModalYouTrackTask(deleteButton.dataset.youtrackTaskDelete || "");
    }
}

function onProjectModalYouTrackTasksBodyInput(event) {
    const summaryInput = event.target.closest("[data-youtrack-task-draft-input]");
    if (summaryInput) {
        projectModalYouTrackTasksState.draftSummary = summaryInput.value;
        if (projectModalYouTrackTasksState.draftError) {
            projectModalYouTrackTasksState.draftError = "";
        }
        return;
    }

    const assigneeSelect = event.target.closest("[data-youtrack-task-draft-assignee]");
    if (assigneeSelect) {
        projectModalYouTrackTasksState.draftAssigneeId = assigneeSelect.value;
        return;
    }

    const dueDateInput = event.target.closest("[data-youtrack-task-draft-due-date]");
    if (dueDateInput) {
        projectModalYouTrackTasksState.draftDueDate = dueDateInput.value;
        return;
    }

    const stateSelect = event.target.closest("[data-youtrack-task-draft-state]");
    if (stateSelect) {
        projectModalYouTrackTasksState.draftState = "value" in stateSelect ? String(stateSelect.value || "") : "";
        return;
    }

    const customFieldInput = event.target.closest("[data-youtrack-task-draft-custom-field]");
    if (customFieldInput) {
        const columnKey = String(customFieldInput.dataset.youtrackTaskDraftCustomField || "").trim();
        setProjectModalDraftCustomFieldValue(columnKey, "value" in customFieldInput ? customFieldInput.value || "" : "");
        return;
    }

    const editInput = event.target.closest("[data-youtrack-task-edit-input]");
    if (editInput) {
        syncProjectModalTaskEditStateFromElement(editInput);
    }
}

function onProjectModalYouTrackTasksBodyKeydown(event) {
    const editField = event.target.closest("[data-youtrack-task-edit-input]");
    if (editField) {
        syncProjectModalTaskEditStateFromElement(editField);

        if (event.key === "Enter") {
            event.preventDefault();
            submitProjectModalYouTrackTaskEdit();
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            cancelProjectModalYouTrackTaskEdit();
            return;
        }
    }

    const draftField = event.target.closest("[data-youtrack-task-draft-input], [data-youtrack-task-draft-assignee], [data-youtrack-task-draft-due-date], [data-youtrack-task-draft-state], [data-youtrack-task-draft-custom-field]");
    if (draftField) {
        if (event.key === "Enter") {
            event.preventDefault();
            submitProjectModalYouTrackTaskDraft();
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closeProjectModalYouTrackTaskDraft();
        }
    }
}

function onProjectModalYouTrackTasksBodyChange(event) {
    const draftAssignee = event.target.closest("[data-youtrack-task-draft-assignee]");
    if (draftAssignee) {
        projectModalYouTrackTasksState.draftAssigneeId = draftAssignee.value;
        return;
    }

    const draftDueDate = event.target.closest("[data-youtrack-task-draft-due-date]");
    if (draftDueDate) {
        projectModalYouTrackTasksState.draftDueDate = draftDueDate.value;
        return;
    }

    const draftState = event.target.closest("[data-youtrack-task-draft-state]");
    if (draftState) {
        projectModalYouTrackTasksState.draftState = draftState.value;
        return;
    }

    const draftCustomField = event.target.closest("[data-youtrack-task-draft-custom-field]");
    if (draftCustomField) {
        const columnKey = String(draftCustomField.dataset.youtrackTaskDraftCustomField || "").trim();
        setProjectModalDraftCustomFieldValue(columnKey, draftCustomField.value || "");
        return;
    }

    const editField = event.target.closest("[data-youtrack-task-edit-input]");
    if (!editField) {
        return;
    }

    syncProjectModalTaskEditStateFromElement(editField);
    const field = String(editField.dataset.youtrackTaskField || "");
    if (
        field === "assignee"
        || field === "state"
        || field === "dueDate"
        || (isDynamicProjectTaskColumnKey(field) && editField instanceof HTMLSelectElement)
    ) {
        submitProjectModalYouTrackTaskEdit();
    }
}

function onProjectModalYouTrackTasksBodyFocusOut(event) {
    const editField = event.target.closest("[data-youtrack-task-edit-input]");
    if (!editField) {
        return;
    }

    syncProjectModalTaskEditStateFromElement(editField);

    const field = String(editField.dataset.youtrackTaskField || "");
    if (
        field !== "summary"
        && !(
            isDynamicProjectTaskColumnKey(field)
            && !(editField instanceof HTMLSelectElement)
        )
    ) {
        return;
    }

    window.setTimeout(() => {
        if (
            projectModalYouTrackTasksState.editingTaskId === String(editField.dataset.youtrackTaskId || "")
            && projectModalYouTrackTasksState.editingField === field
            && !projectModalYouTrackTasksState.editingSubmitting
        ) {
            submitProjectModalYouTrackTaskEdit();
        }
    }, 0);
}

async function submitProjectModalYouTrackTaskEdit() {
    const projectKey = String(dom.projectModal.dataset.youtrackProjectKey || projectModalYouTrackTasksState.projectKey || "").trim();
    const taskId = String(projectModalYouTrackTasksState.editingTaskId || "").trim();
    const field = String(projectModalYouTrackTasksState.editingField || "").trim();
    const value = String(projectModalYouTrackTasksState.editingValue || "").trim();

    if (!projectKey || !taskId || !field || projectModalYouTrackTasksState.editingSubmitting) {
        return;
    }

    if (!canCurrentUserEditProjectTaskTable()) {
        showProjectModalError("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
        return;
    }

    const currentTask = findProjectModalYouTrackTask(taskId);
    if (!currentTask) {
        cancelProjectModalYouTrackTaskEdit();
        return;
    }

    const updates = {};
    if (field === "summary") {
        if (!value) {
            showProjectModalError("Le résumé de la tâche est obligatoire.");
            return;
        }
        if (value === String(currentTask.summary || "").trim()) {
            cancelProjectModalYouTrackTaskEdit();
            return;
        }
        updates.summary = value;
    } else if (field === "assignee") {
        if (value === String(currentTask.assigneeId || "").trim()) {
            cancelProjectModalYouTrackTaskEdit();
            return;
        }
        updates.assigneeId = value;
    } else if (field === "dueDate") {
        if (value === String(currentTask.dueDateInput || "").trim()) {
            cancelProjectModalYouTrackTaskEdit();
            return;
        }
        updates.dueDate = value;
    } else if (field === "state") {
        if (value === String(currentTask.state || "").trim()) {
            cancelProjectModalYouTrackTaskEdit();
            return;
        }
        updates.state = value;
    } else if (isDynamicProjectTaskColumnKey(field)) {
        const currentCustomValue = String(
            currentTask?.customFieldInputValues?.[field]
            ?? currentTask?.customFieldValues?.[field]
            ?? ""
        ).trim();
        if (value === currentCustomValue) {
            cancelProjectModalYouTrackTaskEdit();
            return;
        }
        updates.customField = {
            key: field,
            value,
        };
    } else {
        return;
    }

    projectModalYouTrackTasksState.editingSubmitting = true;
    hideProjectModalError();
    rerenderProjectModalYouTrackTasks();

    try {
        const payload = await updateYouTrackProjectTask(projectKey, taskId, updates);
        if (payload?.task) {
            updateProjectModalYouTrackTaskInMemory(payload.task);
        }
        projectModalYouTrackTasksState.editingTaskId = "";
        projectModalYouTrackTasksState.editingField = "";
        projectModalYouTrackTasksState.editingValue = "";
        projectModalYouTrackTasksState.editingSubmitting = false;
        rerenderProjectModalYouTrackTasks();
    } catch (error) {
        console.error(error);
        projectModalYouTrackTasksState.editingSubmitting = false;
        showProjectModalError(error.message || "Impossible de mettre à jour la tâche YouTrack.");
        rerenderProjectModalYouTrackTasks();
    }
}

async function submitProjectModalYouTrackTaskDraft() {
    const summary = String(projectModalYouTrackTasksState.draftSummary || "").trim();
    const assigneeUserId = String(projectModalYouTrackTasksState.draftAssigneeId || "").trim();
    const dueDate = String(projectModalYouTrackTasksState.draftDueDate || "").trim();
    const stateValue = String(projectModalYouTrackTasksState.draftState || "").trim();
    const customFieldValues = { ...(projectModalYouTrackTasksState.draftCustomFieldValues || {}) };

    if (!canCurrentUserEditProjectTaskTable()) {
        projectModalYouTrackTasksState.draftError = "Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.";
        rerenderProjectModalYouTrackTasks();
        return;
    }

    if (!summary) {
        projectModalYouTrackTasksState.draftError = "Le résumé de la tâche est obligatoire.";
        rerenderProjectModalYouTrackTasks();
        return;
    }

    if (projectModalYouTrackTasksState.mode === "pending") {
        const assignee = getProjectModalAssigneeChoices().find((item) => String(item.id || "") === assigneeUserId);
        const normalizedCustomFieldValues = {};
        Object.entries(customFieldValues).forEach(([columnKey, rawValue]) => {
            const normalizedKey = String(columnKey || "").trim();
            if (!normalizedKey) {
                return;
            }

            normalizedCustomFieldValues[normalizedKey] = String(rawValue || "").trim();
        });

        projectModalYouTrackTasksState.pendingTasks.push({
            summary,
            assigneeId: assigneeUserId,
            assigneeName: String(assignee?.label || "").trim() || "-",
            dueDate,
            state: stateValue,
            customFieldValues: normalizedCustomFieldValues,
            customFieldInputValues: { ...normalizedCustomFieldValues },
        });
        closeProjectModalYouTrackTaskDraft();
        return;
    }

    const projectKey = String(dom.projectModal.dataset.youtrackProjectKey || projectModalYouTrackTasksState.projectKey || "").trim();
    if (!projectKey) {
        projectModalYouTrackTasksState.draftError = "Projet YouTrack introuvable pour la création de tâche.";
        rerenderProjectModalYouTrackTasks();
        return;
    }

    projectModalYouTrackTasksState.draftSubmitting = true;
    projectModalYouTrackTasksState.draftError = "";
    rerenderProjectModalYouTrackTasks();

    try {
        await createYouTrackProjectTask(projectKey, {
            summary,
            description: "",
            assigneeId: assigneeUserId,
            dueDate,
            state: stateValue,
            customFieldValues,
        });
        closeProjectModalYouTrackTaskDraft();
        await syncProjectModalYouTrackTasks({ youtrackId: projectKey });
    } catch (error) {
        console.error(error);
        projectModalYouTrackTasksState.draftSubmitting = false;
        projectModalYouTrackTasksState.draftError = error.message || "Impossible de créer la tâche YouTrack.";
        rerenderProjectModalYouTrackTasks();
    }
}

async function deleteProjectModalYouTrackTask(issueId) {
    const normalizedIssueId = String(issueId || "").trim();
    const projectKey = String(dom.projectModal.dataset.youtrackProjectKey || projectModalYouTrackTasksState.projectKey || "").trim();

    if (!normalizedIssueId || !projectKey) {
        return;
    }

    if (!canCurrentUserEditProjectTaskTable()) {
        showProjectModalError("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
        return;
    }

    const confirmed = window.confirm(`Supprimer définitivement la tâche "${normalizedIssueId}" ?`);
    if (!confirmed) {
        return;
    }

    try {
        await deleteYouTrackProjectTask(projectKey, normalizedIssueId);
        await syncProjectModalYouTrackTasks({ youtrackId: projectKey });
    } catch (error) {
        console.error(error);
        dom.projectModalYouTrackTasksBody.innerHTML = `
            <div class="project-modal-youtrack-tasks-message is-error">
                ${escapeHtml(error.message || "Impossible de supprimer la tâche YouTrack.")}
            </div>
        `;
    }
}

function getPendingProjectModalYouTrackTasksPayload() {
    return projectModalYouTrackTasksState.pendingTasks.map((task) => ({
        summary: String(task.summary || "").trim(),
        description: "",
        assigneeId: String(task.assigneeId || "").trim(),
        dueDate: String(task.dueDate || "").trim(),
        state: String(task.state || "").trim(),
        customFieldValues: { ...(task.customFieldValues || {}) },
    })).filter((task) => task.summary !== "");
}

async function createPendingYouTrackTasks(projectKey, tasks) {
    const normalizedProjectKey = String(projectKey || "").trim();
    if (!normalizedProjectKey || !Array.isArray(tasks) || !tasks.length) {
        return;
    }

    if (!canCurrentUserEditProjectTaskTable()) {
        throw new Error("Lecture seule : vous devez appartenir à l'équipe du projet pour modifier les tâches.");
    }

    for (const task of tasks) {
        const summary = String(task.summary || "").trim();
        if (!summary) {
            continue;
        }

        try {
            await createYouTrackProjectTask(normalizedProjectKey, {
                summary,
                description: "",
                assigneeId: String(task.assigneeId || "").trim(),
                dueDate: String(task.dueDate || "").trim(),
                state: String(task.state || "").trim(),
                customFieldValues: { ...(task.customFieldValues || {}) },
            });
        } catch (error) {
            throw new Error(
                `Le projet YouTrack a bien été créé, mais la tâche "${summary}" n'a pas pu être ajoutée : ${error.message || "erreur inconnue."}`
            );
        }
    }
}

function createEmptyProjectDraft() {
    return normalizeProjectForState({
        id: "",
        ref: "",
        title: "",
        service: "",
        parentProjectId: null,
        projectType: null,
        description: "",
        color: "",
        customColor: "",
        start: null,
        duration: null,
        lane: null,
        startExact: null,
        endExact: null,
        riskGain: null,
        budgetEstimate: null,
        prioritization: null,
        status: "A planifier",
        progression: 0,
        youtrackId: null,
        youtrackUrl: null,
        ownerId: null,
        ownerDisplayName: null,
        ownerEmail: null,
        teamMembers: [],
        taskColumns: [...DEFAULT_PROJECT_TASK_COLUMNS]
    });
}

function normalizePlanningFields(startValue, durationValue) {
    if (!startValue || !Number.isFinite(Number(durationValue))) {
        return {
            start: null,
            duration: null
        };
    }

    if (/^\d{4}-\d{2}$/.test(startValue)) {
        return {
            start: `${startValue}-01`,
            duration: Math.max(1, Number(durationValue) * 2)
        };
    }

    return {
        start: startValue,
        duration: Math.max(1, Number(durationValue))
    };
}

function normalizeDateInputValue(value) {
    if (!value) {
        return "";
    }

    return normalizeStartDate(value);
}

function getProjectEditableDates(project) {
    const exactDates = getProjectStoredExactDates(
        project.start ?? null,
        project.duration ?? null,
        project.startExact ?? null,
        project.endExact ?? null
    );

    return {
        start: exactDates.start || "",
        end: exactDates.end || ""
    };
}

function getProjectStoredExactDates(startValue, durationValue, startExact, endExact) {
    if (!startValue || !durationValue) {
        return {
            start: null,
            end: null
        };
    }

    if (startExact && endExact) {
        return {
            start: normalizeDateInputValue(startExact),
            end: normalizeDateInputValue(endExact)
        };
    }

    const endSlotStart = addHalfMonths(startValue, durationValue - 1);
    return {
        start: normalizeStartDate(startValue),
        end: formatDateInputValue(getHalfMonthEndDate(endSlotStart))
    };
}

function syncProjectExactDatesWithSchedule(project) {
    const exactDates = getProjectStoredExactDates(project.start, project.duration, null, null);
    project.startExact = exactDates.start;
    project.endExact = exactDates.end;
}

function persistState() {
    writeSerializableStateToStorage();
    scheduleProjectsSync();
}

function writeSerializableStateToStorage() {
    const serializable = {
        settings: state.settings,
        projects: state.projects.map(({ id, color, customColor, start, duration, lane, startExact, endExact, progression, status }) => ({
            id,
            color: customColor || "",
            customColor: customColor || "",
            start,
            duration,
            lane,
            startExact,
            endExact,
            status: normalizeProjectStoredStatus(status),
            progression: normalizeProjectProgression(progression)
        }))
    };

    localStorage.setItem(STORAGE_KEY, JSON.stringify(serializable));
}

function readSavedState() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    } catch (error) {
        console.warn("Etat sauvegarde invalide", error);
        return {};
    }
}

function scheduleProjectsSync() {
    if (!appStarted || !state.projects.length) {
        return;
    }

    projectsSyncQueued = true;

    if (projectsSyncTimeout !== null) {
        clearTimeout(projectsSyncTimeout);
    }

    projectsSyncTimeout = window.setTimeout(() => {
        projectsSyncTimeout = null;
        flushProjectsSync();
    }, 150);
}

async function flushProjectsSync() {
    if (!projectsSyncQueued || projectsSyncInFlight) {
        return;
    }

    projectsSyncQueued = false;
    projectsSyncInFlight = true;

    try {
        await apiRequest(API_ROUTES.projects, {
            method: "POST",
            body: JSON.stringify({
                projects: state.projects.map(buildProjectPersistencePayload)
            })
        });
    } catch (error) {
        console.error("Impossible de synchroniser les projets avec le serveur.", error);
    } finally {
        projectsSyncInFlight = false;

        if (projectsSyncQueued) {
            flushProjectsSync();
        }
    }
}

function buildProjectPersistencePayload(project) {
    return {
        id: project.id,
        ref: project.ref,
        title: project.title,
        service: project.service,
        parentProjectId: project.parentProjectId ?? null,
        projectType: normalizeProjectType(project.projectType),
        description: project.description,
        color: project.customColor || "",
        customColor: project.customColor || "",
        start: project.start,
        duration: project.duration,
        lane: project.lane,
        startExact: project.startExact,
        endExact: project.endExact,
        riskGain: project.riskGain ?? null,
        budgetEstimate: project.budgetEstimate ?? null,
        prioritization: project.prioritization ?? null,
        status: normalizeProjectStoredStatus(project.status, project),
        progression: normalizeProjectProgression(project.progression),
        youtrackId: project.youtrackId ?? null,
        youtrackUrl: project.youtrackUrl ?? null,
        ownerId: project.ownerId ?? null,
        ownerDisplayName: project.ownerDisplayName ?? null,
        ownerEmail: project.ownerEmail ?? null,
        teamMembers: getProjectTeamMembers(project),
        taskColumns: getProjectTaskColumns(project)
    };
}

function tokenizeService(service) {
    return service
        .split("/")
        .map((item) => item.trim())
        .filter(Boolean);
}

function getDefaultColor(service) {
    const primaryToken = normalizeServiceKey(tokenizeService(service)[0] || "Autre");
    return state.serviceColors[primaryToken] || SERVICE_COLORS[primaryToken] || "#1d6f74";
}

function normalizeProjectForState(project) {
    const planning = normalizePlanningFields(project.start, project.duration);
    const exactDates = getProjectStoredExactDates(
        planning.start,
        planning.duration,
        project.startExact,
        project.endExact
    );
    const laneValue = project.lane;

    return {
        ...project,
        parentProjectId: normalizeProjectParentId(project.parentProjectId, project.id || ""),
        projectType: normalizeProjectType(project.projectType),
        color: resolveProjectColor(project, {}),
        customColor: resolveProjectCustomColor(project, {}),
        start: planning.start,
        duration: planning.duration,
        lane: Number.isFinite(laneValue) ? Number(laneValue) : null,
        startExact: exactDates.start,
        endExact: exactDates.end,
        youtrackId: project.youtrackId || null,
        youtrackUrl: project.youtrackUrl || null,
        ownerId: project.ownerId || null,
        ownerDisplayName: project.ownerDisplayName || null,
        ownerEmail: project.ownerEmail || null,
        teamMembers: getProjectTeamMembers(project),
        taskColumns: getProjectTaskColumns(project)
    };
}

function getLegacyDefaultColor(service) {
    const primaryToken = normalizeServiceKey(tokenizeService(service)[0] || "Autre");
    return LEGACY_SERVICE_COLORS[primaryToken] || null;
}

function resolveProjectColor(project, savedProject) {
    const customColor = resolveProjectCustomColor(project, savedProject);
    if (customColor) {
        return customColor;
    }

    return getDefaultColor(project.service);
}

function resolveProjectCustomColor(project, savedProject) {
    const defaultColor = normalizeProjectColorHex(getDefaultColor(project.service)) || "";
    const savedCustomColor = normalizeProjectColorHex(savedProject.customColor);
    if (savedCustomColor) {
        return savedCustomColor === defaultColor ? "" : savedCustomColor;
    }

    const savedColor = normalizeProjectColorHex(savedProject.color);
    const seedColor = normalizeProjectColorHex(project.color);
    const legacyDefaultColor = normalizeProjectColorHex(getLegacyDefaultColor(project.service));

    if (savedColor && savedColor !== legacyDefaultColor && savedColor !== defaultColor) {
        return savedColor;
    }

    if (seedColor && seedColor !== defaultColor) {
        return seedColor;
    }

    return "";
}

function setProjectColorFromValue(project, value) {
    const normalizedColor = normalizeProjectColorHex(value);
    if (normalizedColor === null) {
        return false;
    }

    const defaultColor = normalizeProjectColorHex(getDefaultColor(project.service)) || "";
    project.customColor = normalizedColor && normalizedColor !== defaultColor ? normalizedColor : "";
    project.color = project.customColor || getDefaultColor(project.service);
    return true;
}

function normalizeHexColor(value) {
    const normalized = String(value || "").trim().toLowerCase();
    return normalized || "";
}

function normalizeProjectMetaInput(value) {
    const normalized = String(value || "").trim();
    return normalized !== "" ? normalized : null;
}

function normalizeProjectProgression(value) {
    const numericValue = Number(value);
    if (!Number.isFinite(numericValue)) {
        return 0;
    }

    return clamp(Math.round(numericValue / 10) * 10, 0, 100);
}

function normalizeProjectStoredStatus(value, project = null) {
    const normalized = String(value || "").trim();
    const hasSchedule = isScheduled(project || {});

    if (PROJECT_STATUSES.some((status) => status.value === normalized)) {
        if (!hasSchedule && (normalized === "Planifié" || normalized === "En cours")) {
            return "A planifier";
        }

        return normalized;
    }

    return hasSchedule ? "Planifié" : "A planifier";
}

function getProjectStatusReferenceDate() {
    return formatDateInputValue(new Date());
}

function isDateWithinProjectRange(project, referenceDate = getProjectStatusReferenceDate()) {
    if (!isScheduled(project)) {
        return false;
    }

    const editableDates = getProjectEditableDates(project);
    if (!editableDates.start || !editableDates.end) {
        return false;
    }

    const reference = startDateToDate(referenceDate);
    const start = startDateToDate(editableDates.start);
    const end = startDateToDate(editableDates.end);
    return reference >= start && reference <= end;
}

function getProjectEffectiveStatus(project, fallbackStatus = project?.status) {
    const storedStatus = normalizeProjectStoredStatus(fallbackStatus, project);
    if (storedStatus === "Terminé" || storedStatus === "Standby") {
        return storedStatus;
    }

    if (isDateWithinProjectRange(project)) {
        return "En cours";
    }

    return storedStatus;
}

function normalizeProjectStatus(value, project = null) {
    if (project) {
        return getProjectEffectiveStatus(project, value);
    }

    return normalizeProjectStoredStatus(value);
}

function getProjectStatusMeta(value, project = null) {
    const normalizedStatus = normalizeProjectStatus(value, project);
    return PROJECT_STATUSES.find((status) => status.value === normalizedStatus) || PROJECT_STATUSES[0];
}

function normalizeBacklogView(value) {
    return value === "table" ? "table" : "cards";
}

function getBacklogView() {
    return normalizeBacklogView(state.settings.backlogView);
}

function renderProjectStatusOptions(selectedStatus) {
    return PROJECT_STATUSES.map((status) => `
        <option value="${escapeHtml(status.value)}"${status.value === selectedStatus ? " selected" : ""}>${escapeHtml(status.value)}</option>
    `).join("");
}

function populateProjectModalParentOptions(currentProjectId = "", selectedParentId = "") {
    const normalizedCurrentProjectId = String(currentProjectId || "").trim();
    const normalizedSelectedParentId = normalizeProjectParentId(selectedParentId, normalizedCurrentProjectId) || "";
    const parentOptions = state.projects
        .filter((project) => project.id && project.id !== normalizedCurrentProjectId)
        .sort(sortProjectsByLaneThenRef)
        .map((project) => {
            const optionLabel = `${formatProjectParentLabel(project)}${isScheduled(project) ? "" : " (hors timeline)"}`;
            const isDisabled = normalizedCurrentProjectId ? wouldCreateProjectCycle(normalizedCurrentProjectId, project.id) : false;
            return `
                <option value="${escapeHtml(project.id)}"${project.id === normalizedSelectedParentId ? " selected" : ""}${isDisabled ? " disabled" : ""}>
                    ${escapeHtml(optionLabel)}
                </option>
            `;
        })
        .join("");

    dom.projectModalParentInput.innerHTML = `<option value="">Aucun</option>${parentOptions}`;
    dom.projectModalParentInput.value = normalizedSelectedParentId;
}

function getProjectModalSelectedParentProject(currentProjectId = "") {
    const parentProjectId = normalizeProjectParentId(dom.projectModalParentInput?.value, currentProjectId);
    return parentProjectId ? findProject(parentProjectId) : null;
}

function getProjectModalParentHelpMessage(parentProject) {
    if (!parentProject) {
        return PROJECT_MODAL_DEFAULT_HELP;
    }

    if (!isScheduled(parentProject)) {
        return `Le projet parent ${formatProjectParentLabel(parentProject)} n'est pas planifié. Ce sous-projet restera hors timeline tant que le parent n'aura pas de dates.`;
    }

    const parentDates = getProjectDateRange(parentProject);
    return `Sous-projet contraint au planning du parent ${formatProjectParentLabel(parentProject)} : du ${parentDates.start} au ${parentDates.end}.`;
}

function syncProjectModalDisplays() {
    if (!dom.projectModalForm) {
        return;
    }

    const projectId = dom.projectModal.dataset.projectId || "";
    const project = projectId ? findProject(projectId) : null;
    const selectedParentProject = getProjectModalSelectedParentProject(projectId);
    const title = dom.projectModalTitleInput.value.trim() || "Projet";
    const ref = dom.projectModalRefInput.value.trim() || "-";
    const service = dom.projectModalServiceInput.value.trim() || "-";
    const projectType = normalizeProjectType(dom.projectModalTypeInput.value);
    const startValue = normalizeDateInputValue(dom.projectModalStartInput.value);
    const endValue = normalizeDateInputValue(dom.projectModalEndInput.value);
    const start = formatProjectModalDate(startValue);
    const end = formatProjectModalDate(endValue);
    const riskGain = normalizeProjectMetaInput(dom.projectModalRiskGainInput.value);
    const budget = normalizeProjectMetaInput(dom.projectModalBudgetInput.value);
    const prioritization = normalizeProjectMetaInput(dom.projectModalPrioritizationInput.value);
    const storedStatus = normalizeProjectStoredStatus(dom.projectModalStatusInput.value, project);
    const progression = normalizeProjectProgression(dom.projectModalProgressInput.value);
    const description = dom.projectModalDescriptionInput.value.trim();
    const defaultColor = getDefaultColor(service);
    const currentColor = normalizeProjectColorHex(dom.projectModalColorHexInput.value) || defaultColor;
    const statusPreviewProject = {
        ...(project || {}),
        title,
        ref,
        service,
        status: storedStatus,
        start: null,
        duration: null,
        startExact: null,
        endExact: null
    };

    if (startValue && endValue && startDateToDate(startValue) <= startDateToDate(endValue)) {
        const startSlot = snapDateToHalfMonthStart(startValue);
        const endSlot = snapDateToHalfMonthStart(endValue);
        statusPreviewProject.start = startSlot;
        statusPreviewProject.duration = getHalfMonthSlotNumber(endSlot) - getHalfMonthSlotNumber(startSlot) + 1;
        statusPreviewProject.startExact = startValue;
        statusPreviewProject.endExact = endValue;
    }

    const status = getProjectEffectiveStatus(statusPreviewProject, storedStatus);

    dom.projectModalTitle.textContent = title;
    dom.projectModalRefDisplay.textContent = ref;
    dom.projectModalServiceDisplay.textContent = service;
    dom.projectModalTypeDisplay.textContent = projectType || "Non renseigné";
    dom.projectModalParentDisplay.textContent = selectedParentProject
        ? formatProjectParentLabel(selectedParentProject)
        : "Aucun";
    dom.projectModalStartDisplay.textContent = start;
    dom.projectModalEndDisplay.textContent = end;
    dom.projectModalRiskGainDisplay.textContent = formatProjectMeta(riskGain);
    dom.projectModalBudgetDisplay.textContent = formatProjectMeta(budget);
    dom.projectModalPrioritizationDisplay.textContent = formatProjectMeta(prioritization);
    dom.projectModalStatusDisplay.innerHTML = `
        <span class="project-modal-status-display">
            <span class="project-status-badge ${escapeHtml(getProjectStatusMeta(status).className)}">${escapeHtml(status)}</span>
        </span>
    `;
    dom.projectModalProgressDisplay.innerHTML = renderProjectProgressMarkup(progression);
    dom.projectModalDescription.textContent = description || "-";
    dom.projectModalColorDisplay.innerHTML = `
        <span class="project-modal-color-display">
            <span class="project-modal-color-swatch" style="--project-modal-color:${escapeHtml(currentColor)};"></span>
            <span>${escapeHtml(currentColor)}</span>
        </span>
    `;
}

function renderProjectProgressMarkup(progression) {
    const indicatorPosition = getProjectProgressIndicatorPosition(progression);

    return `
        <span class="project-progress-display">
            <span class="project-progress-indicator" style="--progress-left:${indicatorPosition}%;">
                <span class="project-progress-badge">${escapeHtml(String(progression))}%</span>
                <span class="project-progress-pointer"></span>
            </span>
            <span class="project-progress-track">
                <span class="project-progress-fill" style="width:${progression}%;"></span>
            </span>
        </span>
    `;
}

function getProjectProgressIndicatorPosition(progression) {
    if (progression <= 0) {
        return 6;
    }

    if (progression >= 100) {
        return 94;
    }

    return progression;
}

function formatProjectModalDate(value) {
    const normalized = normalizeDateInputValue(value);
    if (!normalized) {
        return "-";
    }

    return formatDateLabel(normalized);
}

function formatDateLabel(value) {
    const date = startDateToDate(value);
    return fullDateFormatter.format(date);
}

function openProjectModalEditor(item) {
    closeProjectModalEditors(item);
    item.classList.add("is-editing");

    const editor = item.querySelector(
        ".project-modal-editor input, .project-modal-editor textarea, .project-modal-editor select, input.project-modal-editor, textarea.project-modal-editor, select.project-modal-editor"
    );
    if (!(editor instanceof HTMLElement)) {
        return;
    }

    requestAnimationFrame(() => {
        editor.focus();
        if (editor instanceof HTMLInputElement || editor instanceof HTMLTextAreaElement) {
            if (editor.type !== "color" && editor.type !== "date") {
                editor.select();
            }
        }
    });
}

function closeProjectModalEditors(exceptItem = null) {
    dom.projectModalForm.querySelectorAll("[data-editable-item].is-editing").forEach((item) => {
        if (item !== exceptItem) {
            item.classList.remove("is-editing");
        }
    });
}

function normalizeProjectColorHex(value) {
    const normalized = normalizeHexColor(value);
    if (!normalized) {
        return "";
    }

    const matched = normalized.match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!matched) {
        return null;
    }

    let hex = matched[1].toLowerCase();
    if (hex.length === 3) {
        hex = hex.split("").map((character) => character + character).join("");
    }

    return `#${hex}`;
}

function applyServiceColors(serviceColors) {
    state.serviceColors = {
        ...SERVICE_COLORS,
        ...normalizeServiceColorMap(serviceColors)
    };

    syncProjectsWithServiceColors();

    if (!state.selectedServiceColor || !state.serviceColors[state.selectedServiceColor]) {
        state.selectedServiceColor = Object.keys(state.serviceColors)[0] || "";
    }
}

function normalizeServiceColorMap(serviceColors) {
    const normalizedMap = {};

    Object.entries(serviceColors || {}).forEach(([service, color]) => {
        const serviceKey = normalizeServiceKey(service);
        const normalizedColor = normalizeHexColor(color);
        if (!serviceKey || !normalizedColor) {
            return;
        }

        normalizedMap[serviceKey] = normalizedColor;
    });

    return normalizedMap;
}

function normalizeServiceKey(value) {
    return value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ")
        .trim();
}

function syncProjectsWithServiceColors() {
    state.projects = state.projects.map((project) => ({
        ...project,
        color: project.customColor || getDefaultColor(project.service)
    }));
}

function syncServiceColorControls() {
    if (!dom.serviceColorSelect || !dom.serviceColorInput) {
        return;
    }

    const services = Object.keys(state.serviceColors).sort((left, right) => left.localeCompare(right, "fr"));
    if (!services.length) {
        dom.serviceColorSelect.innerHTML = `<option value="">Aucun service</option>`;
        dom.serviceColorSelect.disabled = true;
        dom.serviceColorInput.disabled = true;
        return;
    }

    dom.serviceColorSelect.innerHTML = services.map((service) => `
        <option value="${escapeHtml(service)}">${escapeHtml(service)}</option>
    `).join("");

    dom.serviceColorSelect.disabled = false;
    dom.serviceColorInput.disabled = false;

    if (!state.selectedServiceColor || !services.includes(state.selectedServiceColor)) {
        state.selectedServiceColor = services[0];
    }

    const color = state.serviceColors[state.selectedServiceColor] || "#1d6f74";
    dom.serviceColorSelect.value = state.selectedServiceColor;
    dom.serviceColorInput.value = color;
}

function onServiceColorSelectionChange() {
    state.selectedServiceColor = dom.serviceColorSelect.value;
    syncServiceColorControls();
}

async function onServiceColorSave() {
    const service = dom.serviceColorSelect.value;
    const color = normalizeProjectColorHex(dom.serviceColorInput.value);

    if (!service || !color) {
        return;
    }
    dom.serviceColorSelect.disabled = true;
    dom.serviceColorInput.disabled = true;

    try {
        const payload = await saveServiceColor(service, color);
        applyServiceColors(payload?.services || {});
        render();
    } catch (error) {
        console.error(error);
        window.alert(error.message || "Impossible d'enregistrer la couleur du service.");
    } finally {
        syncServiceColorControls();
    }
}

function getCurrentYearMonth() {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}`;
}

function addMonths(yearMonth, delta) {
    const { year, month } = parseYearMonth(yearMonth);
    const date = new Date(year, month - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
}

function getHalfMonthWidth() {
    return getScaledMonthWidth() / 2;
}

function getTotalVisibleSlots() {
    return state.settings.visibleMonths * 2;
}

function getScaledMonthWidth() {
    return Math.round(state.settings.monthWidth * getTimelineZoom());
}

function getTodayMarkerOffset() {
    const today = new Date();
    const currentYearMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}`;
    const monthIndex = monthsBetween(state.settings.timelineStart, currentYearMonth);
    if (monthIndex < 0 || monthIndex > state.settings.visibleMonths - 1) {
        return null;
    }

    const dayIndex = today.getDate() - 1;
    const daysInMonth = getDaysInMonth(today.getFullYear(), today.getMonth() + 1);
    const dayRatio = daysInMonth > 0 ? (dayIndex / daysInMonth) : 0;
    const offset = (monthIndex * getScaledMonthWidth()) + (dayRatio * getScaledMonthWidth());

    return clamp(offset, 0, state.settings.visibleMonths * getScaledMonthWidth());
}

function getTimelineZoom() {
    const numericValue = Number(state.settings.timelineZoom);
    if (!Number.isFinite(numericValue)) {
        return DEFAULT_SETTINGS.timelineZoom;
    }

    return clamp(roundTimelineZoom(numericValue), TIMELINE_ZOOM_MIN, TIMELINE_ZOOM_MAX);
}

function roundTimelineZoom(value) {
    return Math.round(value * 10) / 10;
}

function getTimelineSlotIndex(startDate) {
    if (!startDate) {
        return 0;
    }

    return halfMonthsBetween(state.settings.timelineStart, normalizeStartDate(startDate));
}

function monthsBetween(startYearMonth, endYearMonth) {
    const start = parseYearMonth(startYearMonth);
    const end = parseYearMonth(endYearMonth);
    return (end.year - start.year) * 12 + (end.month - start.month);
}

function halfMonthsBetween(startYearMonth, startDate) {
    const normalizedDate = normalizeStartDate(startDate);
    const { year, month, day } = parseStartDate(normalizedDate);
    return (monthsBetween(startYearMonth, `${year}-${String(month).padStart(2, "0")}`) * 2) + (day >= 15 ? 1 : 0);
}

function parseYearMonth(value) {
    const [year, month] = value.split("-").map(Number);
    return { year, month };
}

function parseStartDate(value) {
    const normalized = normalizeStartDate(value);
    const [year, month, day] = normalized.split("-").map(Number);
    return { year, month, day };
}

function normalizeStartDate(value) {
    if (!value) {
        return "";
    }

    if (/^\d{4}-\d{2}$/.test(value)) {
        return `${value}-01`;
    }

    return value;
}

function yearMonthToDate(value) {
    const { year, month } = parseYearMonth(value);
    return new Date(year, month - 1, 1);
}

function startDateToDate(value) {
    const { year, month, day } = parseStartDate(value);
    return new Date(year, month - 1, day);
}

function getDaysInMonth(year, month) {
    return new Date(year, month, 0).getDate();
}

function slotIndexToStartDate(timelineStart, slotIndex) {
    const baseMonth = addMonths(timelineStart, Math.floor(slotIndex / 2));
    const day = slotIndex % 2 === 0 ? "01" : "15";
    return `${baseMonth}-${day}`;
}

function snapDateToHalfMonthStart(value) {
    const { year, month, day } = parseStartDate(value);
    return `${year}-${String(month).padStart(2, "0")}-${day >= 15 ? "15" : "01"}`;
}

function getHalfMonthSlotNumber(value) {
    const { year, month, day } = parseStartDate(value);
    return (((year * 12) + (month - 1)) * 2) + (day >= 15 ? 1 : 0);
}

function addHalfMonths(startDate, delta) {
    const normalized = normalizeStartDate(startDate);
    const { year, month, day } = parseStartDate(normalized);
    const currentSlot = day >= 15 ? 1 : 0;
    const totalSlots = ((year * 12) + (month - 1)) * 2 + currentSlot + delta;
    const monthIndex = Math.floor(totalSlots / 2);
    const slot = totalSlots % 2;
    const targetYear = Math.floor(monthIndex / 12);
    const targetMonth = (monthIndex % 12) + 1;
    const targetDay = slot === 0 ? "01" : "15";
    return `${targetYear}-${String(targetMonth).padStart(2, "0")}-${targetDay}`;
}

function getHalfMonthEndDate(startDate) {
    const { year, month, day } = parseStartDate(startDate);
    if (day >= 15) {
        return new Date(year, month, 0);
    }

    return new Date(year, month - 1, 14);
}

function getProjectDateRange(project) {
    const editableDates = getProjectEditableDates(project);
    if (!editableDates.start || !editableDates.end) {
        return {
            start: "Non planifié",
            end: "Non planifié"
        };
    }

    return {
        start: fullDateFormatter.format(startDateToDate(editableDates.start)),
        end: fullDateFormatter.format(startDateToDate(editableDates.end))
    };
}

function formatDateInputValue(value) {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, "0");
    const day = String(value.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

function formatDuration(duration) {
    return `${duration} mois`;
}

function formatProjectMeta(value) {
    const normalized = String(value || "").trim();
    return normalized || "-";
}

function formatTimelineZoom(value) {
    return `${Math.round(value * 100)}%`;
}

function getTimelineParentDropTargetFromElement(targetElement, draggedProjectId = "") {
    if (!(targetElement instanceof Element)) {
        return null;
    }

    if (targetElement.closest("[data-unschedule]")) {
        return null;
    }

    const row = targetElement.closest(".timeline-row[data-project-id]");
    if (!row) {
        return null;
    }

    const projectId = String(row.dataset.projectId || "").trim();
    if (!projectId || projectId === String(draggedProjectId || "").trim()) {
        return null;
    }

    const isProjectLabelTarget = Boolean(targetElement.closest(".row-label"));
    const isProjectBarTarget = Boolean(targetElement.closest("[data-bar-id]"));
    if (!isProjectLabelTarget && !isProjectBarTarget) {
        return null;
    }

    return { row, projectId };
}

function getTimelineParentDropTargetFromPoint(clientX, clientY, draggedProjectId = "") {
    return getTimelineParentDropTargetFromElement(document.elementFromPoint(clientX, clientY), draggedProjectId);
}

function getTimelineRowReorderDropTargetFromPoint(clientX, clientY, draggedProjectId = "") {
    return getTimelineRowReorderDropTarget({
        clientY,
        target: document.elementFromPoint(clientX, clientY)
    }, draggedProjectId);
}

function syncPointerTimelineDropTarget(clientX, clientY, draggedProjectId = "") {
    clearDropTargets();
    const rowReorderDropTarget = getTimelineRowReorderDropTargetFromPoint(clientX, clientY, draggedProjectId);
    if (rowReorderDropTarget?.row) {
        rowReorderDropTarget.row.classList.add(
            rowReorderDropTarget.position === "before"
                ? "is-row-reorder-target-before"
                : "is-row-reorder-target-after"
        );
        return;
    }

    const parentDropTarget = getTimelineParentDropTargetFromPoint(clientX, clientY, draggedProjectId);
    if (parentDropTarget?.row) {
        parentDropTarget.row.classList.add("is-child-drop-target");
    }
}

function clearDropTargets() {
    dom.timelineRows.querySelectorAll(".is-drop-target, .is-child-drop-target, .is-row-reorder-target-before, .is-row-reorder-target-after").forEach((row) => {
        row.classList.remove("is-drop-target");
        row.classList.remove("is-child-drop-target");
        row.classList.remove("is-row-reorder-target-before");
        row.classList.remove("is-row-reorder-target-after");
    });
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function cssEscape(value) {
    if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
        return CSS.escape(String(value || ""));
    }

    return String(value || "").replace(/["\\]/g, "\\$&");
}
