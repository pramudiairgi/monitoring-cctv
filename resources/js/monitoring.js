const DATA_SCRIPT = document.getElementById("monitoring-data");
const CAMERAS = DATA_SCRIPT ? JSON.parse(DATA_SCRIPT.textContent) : [];

const PLAYBACK_CONFIG_SCRIPT = document.getElementById("playback-config");
let PLAYBACK_CONFIG = {};
try {
    PLAYBACK_CONFIG = PLAYBACK_CONFIG_SCRIPT
        ? JSON.parse(PLAYBACK_CONFIG_SCRIPT.textContent)
        : {};
} catch {
    PLAYBACK_CONFIG = {};
}

function playbackInt(key, fallback) {
    const raw = PLAYBACK_CONFIG[key];
    const parsed = typeof raw === "number" ? raw : parseInt(raw, 10);
    return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
}

function playbackString(key, fallback) {
    const raw = PLAYBACK_CONFIG[key];
    return typeof raw === "string" && raw.length > 0 ? raw : fallback;
}

const NAVBAR_HIDE_DELAY = 2000;
const NAVBAR_HIDE_DELAY_GRID = 2000;
const TELEMETRY_ENDPOINT = "/api/telemetry";
const TELEMETRY_FLUSH_INTERVAL = 300000;
const TELEMETRY_FLUSH_THRESHOLD = 50;
const STORAGE_KEY_SELECTION = "camera-selection";

let cameras = CAMERAS;
let camerasMap = new Map(cameras.map((c) => [c.id, c]));
let currentCameraStates = {};
let searchQuery = "";
const CATEGORY_SET_KEY = "monitoring.categories.v1";
const allCategorySlugs = () => [...new Set(cameras.map((c) => c.category))];
function loadCategorySet() {
    try {
        const raw = JSON.parse(localStorage.getItem(CATEGORY_SET_KEY) || "null");
        if (Array.isArray(raw)) {
            const known = new Set(allCategorySlugs());
            const valid = raw.filter((s) => typeof s === "string" && known.has(s));
            if (valid.length > 0) return new Set(valid);
        }
    } catch {
        /* abaikan, pakai semua */
    }
    return new Set(allCategorySlugs());
}
function persistCategorySet() {
    try {
        localStorage.setItem(CATEGORY_SET_KEY, JSON.stringify([...selectedCategories]));
    } catch {}
}
let selectedCategories = loadCategorySet();
let selectedStatus = "online";
let fullscreenCameraId = null;
let gridFullscreen = false;
let cameraSelection = null;
let navbarTimeout = null;
let streamManagers = new Map();
let initQueue = new Map();
let observer = null;
let _polling = false;
let _statusChanged = false;
const STREAM_CONCURRENCY = 6;
let tempFullscreenId = null;
const cancelledTempIds = new Set();
const MAX_AUTO_PLAY_DESKTOP = playbackInt("playback_max_desktop", 9);
const MAX_AUTO_PLAY_MOBILE_PORTRAIT = playbackInt(
    "playback_max_mobile_portrait",
    4,
);
const MAX_AUTO_PLAY_MOBILE_LANDSCAPE = playbackInt(
    "playback_max_mobile_landscape",
    6,
);

const PATROL_ALERT_SCRIPT = document.getElementById("patrol-alert-data");
const PATROL_ALERT = PATROL_ALERT_SCRIPT
    ? JSON.parse(PATROL_ALERT_SCRIPT.textContent)
    : { live: false, total: 0, online: 0, offline: 0 };

let patrolToastTimer = null;

function showPatrolToast() {
    const toast = document.getElementById("patrol-toast");
    if (!toast) return;
    toast.hidden = false;
    clearTimeout(patrolToastTimer);
    patrolToastTimer = setTimeout(() => {
        toast.hidden = true;
    }, 7000);
}

function initPatrolToast() {
    const toast = document.getElementById("patrol-toast");
    if (!toast) return;

    const dismissBtn = document.getElementById("patrol-toast-dismiss");
    dismissBtn?.addEventListener("click", () => {
        toast.hidden = true;
        clearTimeout(patrolToastTimer);
    });

    if (PATROL_ALERT.live) {
        showPatrolToast();
    }
}

function pollPatrolToast() {
    fetch("/cameras.json", { priority: "low" })
        .then((res) => res.json())
        .then((data) => {
            const patrolCameras = data.cameras?.filter(
                (c) => (c.category || "").toLowerCase() === PRIORITY_CATEGORY
            ) ?? [];
            const liveCount = patrolCameras.filter(
                (c) => c.status === "online"
            ).length;
            if (liveCount > 0) {
                const toast = document.getElementById("patrol-toast");
                if (toast && toast.hidden) {
                    const desc = toast.querySelector(".patrol-toast-desc");
                    if (desc) {
                        desc.textContent = `${liveCount}/${patrolCameras.length} kamera patroli sedang live`;
                    }
                    showPatrolToast();
                }
            }
        })
        .catch(() => {});
}

setInterval(pollPatrolToast, 30000);
const STAGGER_DELAY_MS = playbackInt("playback_stagger_ms", 350);
const PRIORITY_CATEGORY = playbackString(
    "playback_priority_category",
    "patroli",
).toLowerCase();
let _activeStreamInit = 0;
let _streamQueue = [];

const grid = document.getElementById("camera-grid");
const cameraNames = new Map(cameras.map((c) => [c.id, c.name]));
const searchInput = document.getElementById("search");
const statusFilter = document.getElementById("status-filter");
const liveCounter = document.getElementById("live-counter");
const manualPlayIds = new Set();
const mutedIds = new Set();
const cameraCount = document.getElementById("camera-count");
const navbar = document.getElementById("navbar");

const telemetry = {
    queue: [],
    timer: null,

    init() {
        this.timer = setInterval(() => this.flush(), TELEMETRY_FLUSH_INTERVAL);
        window.addEventListener("beforeunload", () => {
            this.flush();
        });
    },

    track(data) {
        const payload = {
            ...data,
            camera_name: cameraNames.get(data.camera_id) || null,
            user_agent: navigator.userAgent,
            timestamp: Date.now(),
        };
        this.queue.push(payload);
        if (this.queue.length >= TELEMETRY_FLUSH_THRESHOLD) {
            this.flush();
        }
    },

    flush() {
        if (this.queue.length === 0) return;
        const batch = this.queue.splice(0, this.queue.length);
        const blob = new Blob([JSON.stringify(batch)], {
            type: "application/json",
        });
        if (!navigator.sendBeacon(TELEMETRY_ENDPOINT, blob)) {
            fetch(TELEMETRY_ENDPOINT, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(batch),
                keepalive: true,
            }).catch(() => {});
        }
    },

    destroy() {
        clearInterval(this.timer);
        this.flush();
    },
};

function getFilteredIds() {
    let filtered = cameras;

    if (selectedStatus === "online") {
        filtered = filtered.filter((c) => c.status === "online");
    } else if (selectedStatus === "offline") {
        filtered = filtered.filter((c) => c.status !== "online");
    }

    if (selectedCategories.size > 0) {
        filtered = filtered.filter((c) => selectedCategories.has(c.category));
    }

    if (searchQuery) {
        const q = searchQuery.toLowerCase();
        filtered = filtered.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.category.toLowerCase().includes(q),
        );
    }

    if (cameraSelection !== null) {
        filtered = filtered.filter((c) => cameraSelection.has(c.id));
    }

    return new Set(filtered.map((c) => c.id));
}

function updateBadge(cell, status) {
    cell.dataset.status = status;
    const camId = parseInt(cell.dataset.id, 10);
    const camera = camerasMap.get(camId);
    const displayName = camera?.name || "";
    const badge = cell.querySelector(".status-badge");
    if (badge) {
        badge.className = `status-badge ${status}`;
        const badgeName = badge.querySelector(".badge-name");
        if (badgeName) badgeName.textContent = displayName;
        const badgeStatus = badge.querySelector(".badge-status");
        if (badgeStatus) badgeStatus.textContent = ` - ${status}`;
    }
    cell.setAttribute("aria-label", `${displayName} - ${status}`);
}

function cameraPriority(c) {
    return c.status === "online" ? 0 : 1;
}

function touchLandscapeDock() {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    return vw <= 1400 && vw > vh && window.matchMedia("(hover: none)").matches;
}

function getMaxAutoPlayRaw() {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    if (vw > 768 && !touchLandscapeDock()) return MAX_AUTO_PLAY_DESKTOP;
    return vw > vh ? MAX_AUTO_PLAY_MOBILE_LANDSCAPE : MAX_AUTO_PLAY_MOBILE_PORTRAIT;
}

// Effective cap from admin setting per device. Admin 0 = unlimited.
function getMaxAutoPlay() {
    const serverRaw = getMaxAutoPlayRaw();
    if (!serverRaw || serverRaw <= 0) return Infinity;
    return serverRaw;
}

// Synchronous count of playing + starting streams. initQueue entries are
// reserved synchronously in initStream(), so this stays accurate while
// streamManagers.set() still lags behind in the async queue worker.
function activeStreamCount() {
    let n = 0;
    streamManagers.forEach((_, id) => {
        if (!manualPlayIds.has(id)) n++;
    });
    initQueue.forEach((_, id) => {
        if (!manualPlayIds.has(id)) n++;
    });
    return n;
}

function isPriorityCamera(c) {
    return (c.category || "").toLowerCase() === PRIORITY_CATEGORY;
}

function getAutoPlayTargets() {
    const max = getMaxAutoPlay();
    const visibleIds = getFilteredIds();
    return cameras
        .filter((c) => visibleIds.has(c.id) && c.status === "online")
        .sort((a, b) => {
            const pa = isPriorityCamera(a) ? 0 : 1;
            const pb = isPriorityCamera(b) ? 0 : 1;
            if (pa !== pb) return pa - pb;
            return cameraPriority(a) - cameraPriority(b);
        })
        .slice(0, max);
}

function initStaggeredBurst() {
    const targets = getAutoPlayTargets();
    targets.forEach((camera, index) => {
        setTimeout(() => {
            if (camera.status !== "online") return;
            if (streamManagers.has(camera.id) || initQueue.has(camera.id))
                return;
            if (activeStreamCount() >= getMaxAutoPlay()) return;
            const cell = document.querySelector(
                `.camera-cell[data-id="${camera.id}"]`,
            );
            if (!cell || cell.style.display === "none") return;
            initStream(cell, camera, camera.target_url);
        }, index * STAGGER_DELAY_MS);
    });
}



function onStreamEnded(cameraId) {
    const camera = camerasMap.get(cameraId);
    if (!camera) return;

    const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
    if (cell) {
        markCameraOffline(camera, cell);
    }
    applyFilters();
}

function cleanupStreamManagers() {
    streamManagers.forEach((manager) => manager.destroy());
    streamManagers.clear();
}

function categoryColor(cat) {
    let hash = 0;
    for (let i = 0; i < cat.length; i++) {
        hash = cat.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 60%, 60%)`;
}

function escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
}

function markCameraOffline(camera, cell) {
    streamManagers.delete(camera.id);
    camera.status = "offline";
    currentCameraStates[camera.id] = "offline";

    cell.style.order = "";
    const placeholder = cell.querySelector(".camera-placeholder");
    const pt = placeholder?.querySelector(".placeholder-text");
    if (pt) pt.textContent = `${camera.name} - Patroli Offline`;
    setPlaceholderIcon(cell, "offline");
    placeholder?.classList.remove("placeholder-hidden");
    cell.querySelector("video").style.display = "none";
    updateBadge(cell, "offline");
}

function processStreamQueue() {
    while (_streamQueue.length > 0 && _activeStreamInit < STREAM_CONCURRENCY) {
        const next = _streamQueue.shift();
        _activeStreamInit++;
        next().finally(() => {
            _activeStreamInit--;
            processStreamQueue();
        });
    }
}

async function initStream(cell, camera, targetUrl) {
    targetUrl = targetUrl || camera.stream_url;
    const video = cell.querySelector("video");

    if (camera.status !== "online") {
        markCameraOffline(camera, cell);
        return;
    }

    const prev = streamManagers.get(camera.id);
    if (prev) {
        prev.destroy();
        streamManagers.delete(camera.id);
    }

    if (initQueue.has(camera.id)) {
        await initQueue.get(camera.id);
        if (streamManagers.has(camera.id)) return;
    }

    let _unlock;
    const lock = new Promise((r) => {
        _unlock = r;
    });
    initQueue.set(camera.id, lock);

    _streamQueue.push(async () => {
        try {
            const stillOnline = camera.status === "online";
            if (!stillOnline || cell.style.display === "none") {
                _unlock();
                return;
            }
            if (cancelledTempIds.has(camera.id)) {
                cancelledTempIds.delete(camera.id);
                _unlock();
                return;
            }

            const collision = streamManagers.get(camera.id);
            if (collision) {
                collision.destroy();
                streamManagers.delete(camera.id);
            }

            const { default: StreamManager } = await import("./stream-manager.js");

            const isAdaptive =
                Boolean(camera.adaptive_url) ||
                (camera.category || "").toLowerCase() === PRIORITY_CATEGORY;

            const manager = new StreamManager(
                camera.id,
                video,
                targetUrl,
                telemetry,
                onStreamEnded,
                isAdaptive,
            );

            manager.attachMedia();
            streamManagers.set(camera.id, manager);
            refreshCellStates();
        } finally {
            initQueue.delete(camera.id);
            _unlock();
        }
    });

    processStreamQueue();
}

function createCameraCell(camera) {
    const cell = document.createElement("div");
    cell.className = "camera-cell";
    cell.dataset.id = String(camera.id);
    cell.dataset.name = (camera.name || "").toLowerCase();
    cell.dataset.category = camera.category || "";
    cell.dataset.status = camera.status || "offline";
    cell.tabIndex = 0;
    cell.setAttribute("role", "button");
    cell.setAttribute("aria-label", `${camera.name || ""} - ${camera.status || ""}`);
    if (camera.status === "online") {
        cell.style.order = "-1";
    }
    cell.innerHTML = `
        <div class="camera-placeholder">
          <div class="placeholder-status-row">
            <span class="placeholder-icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="M10 9l5 3-5 3V9z"/>
            </svg>
            </span>
            <span class="placeholder-text">Loading stream...</span>
          </div>
          <span class="placeholder-caption">${escapeHtml(camera.name || "")}</span>
          <button class="cell-play-btn" style="display:none" aria-label="Putar tayangan"></button>
        </div>
        <video muted autoplay playsinline></video>
        <button class="cell-pause-btn" style="display:none" aria-label="Berhenti memutar"></button>
        <button class="cell-speak-toggle" style="display:none" aria-label="Nyalakan suara"></button>
        <div class="camera-placeholder-info">
          <span class="status-badge ${escapeHtml(camera.status || "offline")}"><span class="badge-name">${escapeHtml(camera.name || "")}</span><span class="badge-status"> - ${escapeHtml(camera.status || "")}</span></span>
        </div>
        <button class="fullscreen-close" aria-label="Exit fullscreen">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
          </svg>
        </button>`;
    const video = cell.querySelector("video");
    if (video) {
        video.muted = true;
        video.autoplay = true;
        video.playsInline = true;
    }
    return cell;
}

async function pollLocalJson() {
    if (document.hidden) return;
    if (_polling) return;
    _polling = true;
    const newCameras = [];
    try {
        const res = await fetch("/cameras.json", { priority: "low" });
        if (!res.ok) return;
        const data = await res.json();

        for (const newCam of data.cameras) {
            const oldCam = camerasMap.get(newCam.id);
            if (!oldCam) {
                const camera = {
                    id: newCam.id,
                    name: newCam.name,
                    stream_url: newCam.stream_url,
                    adaptive_url: newCam.adaptive_url || null,
                    target_url: newCam.target_url || newCam.stream_url,
                    category: newCam.category || "",
                    status: newCam.status,
                };
                cameras.push(camera);
                camerasMap.set(camera.id, camera);
                cameraNames.set(camera.id, camera.name);
                currentCameraStates[camera.id] = camera.status;
                const cell = createCameraCell(camera);
                grid.appendChild(cell);
                if (observer) observer.observe(cell);
                newCameras.push(camera);
                _statusChanged = true;
                continue;
            }

            const prevStatus = currentCameraStates[newCam.id];
            const newStatus = newCam.status;
            const statusChanged = prevStatus !== newStatus;
            if (statusChanged) _statusChanged = true;

            oldCam.status = newStatus;
            if (newCam.target_url) oldCam.target_url = newCam.target_url;

            const cell = document.querySelector(
                `.camera-cell[data-id="${newCam.id}"]`,
            );
            if (!cell) continue;

            if (newStatus === "online") {
                if (prevStatus === "offline") {
                    cell.querySelector("video").style.display = "";
                    const overlay = cell.querySelector(".camera-offline-overlay");
                    if (overlay) overlay.style.display = "none";
                    updateBadge(cell, "online");
                    cell.style.order = "-1";

                    if (
                        cell.style.display !== "none" &&
                        (streamManagers.has(oldCam.id) ||
                            activeStreamCount() < getMaxAutoPlay())
                    ) {
                        await initStream(cell, oldCam, oldCam.target_url);
                    }
                } else {
                    updateBadge(cell, "online");
                    cell.style.order = "-1";
                    if (
                        !streamManagers.has(newCam.id) &&
                        cell.style.display !== "none" &&
                        activeStreamCount() < getMaxAutoPlay()
                    ) {
                        await initStream(cell, oldCam, oldCam.target_url);
                    }
                }
            } else {
                if (prevStatus === "online") {
                    const mgr = streamManagers.get(newCam.id);
                    if (mgr) {
                        mgr.destroy();
                        streamManagers.delete(newCam.id);
                    }
                    cell.style.order = "";
                    const ph = cell.querySelector(".camera-placeholder");
                    const pt = ph?.querySelector(".placeholder-text");
                    if (pt) pt.textContent = `${newCam.name} - Offline`;
                    setPlaceholderIcon(cell, "offline");
                    cell.querySelector("video").style.display = "none";
                    const overlay = cell.querySelector(".camera-offline-overlay");
                    if (overlay) overlay.style.display = "";
                }
                updateBadge(cell, "offline");
            }

            currentCameraStates[newCam.id] = newStatus;
        }

        if (_statusChanged) {
            _statusChanged = false;
            applyFilters();
            if (newCameras.length > 0) {
                newCameras
                    .sort((a, b) => {
                        const pa = isPriorityCamera(a) ? 0 : 1;
                        const pb = isPriorityCamera(b) ? 0 : 1;
                        if (pa !== pb) return pa - pb;
                        return cameraPriority(a) - cameraPriority(b);
                    })
                    .forEach((camera, index) => {
                        setTimeout(() => {
                            if (camera.status !== "online") return;
                            if (streamManagers.has(camera.id) || initQueue.has(camera.id))
                                return;
                            if (activeStreamCount() >= getMaxAutoPlay()) return;
                            const cell = document.querySelector(
                                `.camera-cell[data-id="${camera.id}"]`,
                            );
                            if (!cell || cell.style.display === "none") return;
                            initStream(cell, camera, camera.target_url);
                        }, index * STAGGER_DELAY_MS);
                    });
            }
        }
    } catch {
        /* silent */
    } finally {
        _polling = false;
        refreshCellStates();
    }
}

function initObserver() {
    if (observer) observer.disconnect();

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const cell = entry.target;
                    const camId = parseInt(cell.dataset.id, 10);
                    const camera = camerasMap.get(camId);
                    if (
                        camera &&
                        camera.status === "online" &&
                        !streamManagers.has(camId) &&
                        !initQueue.has(camId) &&
                        activeStreamCount() < getMaxAutoPlay() &&
                        getAutoPlayTargets().some((c) => c.id === camId)
                    ) {
                        initStream(cell, camera, camera.target_url);
                    }
                }
            });
        },
        { rootMargin: "300px" },
    );

    document.querySelectorAll(".camera-cell").forEach((cell) => {
        observer.observe(cell);
    });
}

function applyFilters() {
    const visibleIds = getFilteredIds();
    let visibleCount = 0;

    grid.querySelectorAll(".camera-cell").forEach((cell) => {
        const id = parseInt(cell.dataset.id);
        const show = visibleIds.has(id);
        if (!show) {
            const mgr = streamManagers.get(id);
            if (mgr) {
                mgr.destroy();
                streamManagers.delete(id);
            }
        }
        cell.style.display = show ? "" : "none";
        if (show) visibleCount++;
    });

    const vw = window.innerWidth;
    const vh = grid.clientHeight || window.innerHeight;
    // Lanskap pendek (HP landscape): tampilkan sebagai umpan scroll
    // vertikal alih-alih force-fit yang menghasilkan irisan tipis.
    const shortLandscape = vh <= 500 && vw > vh;
    grid.classList.toggle("scroll-feed", shortLandscape);
    const targetAspect = 16 / 9;
    const isMobile = vw <= 768 || touchLandscapeDock();
    const isLandscape = vw > vh;
    let maxCols;
    if (isMobile && isLandscape) {
        maxCols = 3;
    } else if (isMobile) {
        maxCols = 2;
    } else {
        maxCols = 4;
    }

    let cols, rows;

    if (visibleCount <= 1) {
        cols = 1;
        rows = 1;
    } else {
        let bestDiff = Infinity;
        cols = 1;
        rows = visibleCount;

        for (let c = 1; c <= Math.min(visibleCount, maxCols); c++) {
            const r = Math.ceil(visibleCount / c);
            const cellW = vw / c;
            const cellH = vh / r;
            const aspect = cellW / cellH;
            const diff = Math.abs(aspect - targetAspect);

            if (diff < bestDiff) {
                bestDiff = diff;
                cols = c;
                rows = r;
            }
        }

        const remainder = visibleCount % cols;
        const cells = grid.querySelectorAll(
            '.camera-cell:not([style*="display: none"])',
        );
        cells.forEach((cell) => (cell.style.justifySelf = ""));
        if (remainder !== 0) {
            const lastRowStart = cells.length - remainder;
            for (let i = lastRowStart; i < cells.length; i++) {
                cells[i].style.justifySelf = "start";
            }
        }
    }

    grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    grid.style.gridTemplateRows = `repeat(${rows}, 1fr)`;
    if (shortLandscape) {
        const feedCols = vw < 600 ? 1 : 2;
        grid.style.gridTemplateColumns = `repeat(${feedCols}, 1fr)`;
        const feedGap = parseFloat(getComputedStyle(grid).rowGap) || 0;
        const feedW = grid.clientWidth || vw;
        const feedRowH = Math.max(
            120,
            Math.round((((feedW - feedGap * (feedCols - 1)) / feedCols) * 9) / 16),
        );
        const feedRows = Math.max(1, Math.ceil(visibleCount / feedCols));
        grid.style.gridTemplateRows = `repeat(${feedRows}, ${feedRowH}px)`;
    }

    let emptyMsg = grid.querySelector(".empty-message");
    if (visibleCount === 0) {
        initQueue.clear();
        if (!emptyMsg) {
            emptyMsg = document.createElement("div");
            emptyMsg.className = "empty-message";
            emptyMsg.textContent = "No cameras match your filter";
            grid.appendChild(emptyMsg);
        }
        emptyMsg.style.display = "flex";
    } else if (emptyMsg) {
        emptyMsg.style.display = "none";
    }

    if (cameraCount) {
        const selected = cameraSelection === null ? cameras.length : cameraSelection.size;
        cameraCount.textContent = `${visibleCount} visible / ${selected} selected`;
    }
    syncCategoryDropdowns();
    refreshCellStates();
}

// Temp fullscreen stream: plays a capped (non-playing) online camera only
// while it is fullscreen. Destroyed on exit/switch so the grid returns to
// the autoplay set.
async function ensureTempFullscreenStream(cameraId) {
    const camera = camerasMap.get(cameraId);
    if (!camera || camera.status !== "online") return;
    const existing = streamManagers.get(cameraId);
    if (existing) {
        if (existing._suspended) existing.resume();
        return;
    }
    const cell = document.querySelector(
        `.camera-cell[data-id="${cameraId}"]`,
    );
    if (!cell) return;
    cancelledTempIds.delete(cameraId);
    tempFullscreenId = cameraId;
    try {
        await initStream(cell, camera, camera.target_url);
    } catch {
        /* offline/dead stream — placeholder stays */
    }
    if (fullscreenCameraId !== cameraId) {
        cleanupTempFullscreenStream(cameraId);
    }
}

function cleanupTempFullscreenStream(cameraId) {
    const id = cameraId ?? tempFullscreenId;
    if (id === null || id === undefined) return;
    if (id === tempFullscreenId) tempFullscreenId = null;
    cancelledTempIds.add(id);
    const mgr = streamManagers.get(id);
    if (mgr) {
        mgr.destroy();
        streamManagers.delete(id);
    }
    if (fullscreenCameraId !== id) {
        resetCellToPlaceholder(id);
    }
}

function resetCellToPlaceholder(id) {
    const cell = document.querySelector(`.camera-cell[data-id="${id}"]`);
    if (!cell) return;
    const camera = camerasMap.get(id);
    if (!camera || camera.status !== "online") return;
    const ph = cell.querySelector(".camera-placeholder");
    const pt = ph?.querySelector(".placeholder-text");
    if (pt) pt.textContent = "Online \u2014 klik untuk putar";
    setPlaceholderIcon(cell, "hidden");
    ph?.classList.remove("placeholder-hidden");
    const video = cell.querySelector("video");
    if (video) video.style.display = "none";
    updatePlayButton(cell, camera);
    updateSpeakerButton(cell, camera);
}

function enforceAutoPlayCap() {
    const max = getMaxAutoPlay();
    if (!Number.isFinite(max)) return;
    const allowed = new Set(getAutoPlayTargets().map((c) => c.id));
    streamManagers.forEach((mgr, id) => {
        if (manualPlayIds.has(id) || allowed.has(id)) return;
        mgr.destroy();
        streamManagers.delete(id);
        resetCellToPlaceholder(id);
    });
    updateLiveCounter();
}

const ICON_PLAY = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="6 3 20 12 6 21 6 3"/></svg>`;
const ICON_STOP = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="12" height="12" x="6" y="6" rx="1"/></svg>`;

const ICON_OFFLINE = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.66 6H14a2 2 0 0 1 2 2v2.34l1 1L22 8v8"/><path d="M16 16a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h2l10 10Z"/><line x1="2" x2="22" y1="2" y2="22"/></svg>`;

function setPlaceholderIcon(cell, kind) {
    const icon = cell.querySelector(".camera-placeholder .placeholder-icon");
    if (!icon) return;
    if (kind === "hidden") {
        icon.style.display = "none";
        return;
    }
    icon.style.display = "";
    icon.classList.toggle("offline", kind === "offline");
    icon.innerHTML =
        kind === "offline" ? ICON_OFFLINE : `<span class="placeholder-spinner"></span>`;
}

function updatePlayButton(cell, camera) {
    let playBtn = cell.querySelector(".cell-play-btn");
    if (!playBtn) {
        playBtn = document.createElement("button");
        playBtn.className = "cell-play-btn";
        playBtn.style.display = "none";
        playBtn.setAttribute("aria-label", "Putar tayangan");
        cell.querySelector(".camera-placeholder")?.appendChild(playBtn);
    }
    let pauseBtn = cell.querySelector(".cell-pause-btn");
    if (!pauseBtn) {
        pauseBtn = document.createElement("button");
        pauseBtn.className = "cell-pause-btn";
        pauseBtn.style.display = "none";
        pauseBtn.setAttribute("aria-label", "Berhenti memutar");
        const info = cell.querySelector(".camera-placeholder-info");
        if (info) info.before(pauseBtn);
        else cell.appendChild(pauseBtn);
    }
    const online = camera.status === "online";
    const playing = streamManagers.has(camera.id);
    const isManual = manualPlayIds.has(camera.id);
    const showPlay = online && !playing;
    const showPause = online && playing && isManual;
    playBtn.style.display = showPlay ? "" : "none";
    if (showPlay) {
        playBtn.innerHTML = `${ICON_PLAY}<span>Putar</span>`;
        playBtn.setAttribute("aria-label", "Putar tayangan");
    }
    pauseBtn.style.display = showPause ? "" : "none";
    if (showPause) pauseBtn.innerHTML = ICON_STOP;
    cell.classList.toggle("manual-playing", showPause);
}

function refreshCellStates() {
    const targets = new Set(getAutoPlayTargets().map((c) => c.id));
    document.querySelectorAll(".camera-cell").forEach((cell) => {
        const id = parseInt(cell.dataset.id, 10);
        const camera = camerasMap.get(id);
        if (!camera || camera.status !== "online") {
            if (camera) updatePlayButton(cell, camera);
            return;
        }
        if (streamManagers.has(id)) {
            updatePlayButton(cell, camera);
            updateSpeakerButton(cell, camera);
            const pm = streamManagers.get(id);
            if (pm && pm.userPaused) {
                const ph2 = cell.querySelector(".camera-placeholder");
                const pt2 = ph2?.querySelector(".placeholder-text");
                if (pt2) pt2.textContent = "Dijeda";
                ph2?.classList.remove("placeholder-hidden");
                const pb = cell.querySelector(".cell-play-btn");
                if (pb) pb.style.display = "none";
                const sb2 = cell.querySelector(".cell-pause-btn");
                if (sb2) sb2.style.display = manualPlayIds.has(id) ? "" : "none";
            }
            return;
        }
        if (targets.has(id)) {
            const pt = cell.querySelector(".camera-placeholder .placeholder-text");
            if (pt) pt.textContent = "Memuat tayangan...";
            setPlaceholderIcon(cell, "loading");
            cell.querySelectorAll(".cell-play-btn, .cell-pause-btn").forEach((b) => {
                b.style.display = "none";
            });
            return;
        }
        resetCellToPlaceholder(id);
    });
    updateLiveCounter();
    updateFsBar();
}

function updateLiveCounter() {
    if (!liveCounter) return;
    let online = 0;
    cameras.forEach((c) => {
        const cell = document.querySelector(`.camera-cell[data-id="${c.id}"]`);
        if (cell && cell.style.display !== "none" && c.status === "online") online++;
    });
    liveCounter.textContent = `Live ${streamManagers.size}/${online}`;
}

function toggleManualPlay(cameraId) {
    const camera = camerasMap.get(cameraId);
    if (!camera || camera.status !== "online") return;
    if (manualPlayIds.has(cameraId) || streamManagers.has(cameraId)) {
        if (manualPlayIds.has(cameraId)) {
            if (fullscreenCameraId === cameraId) exitFullscreen();
            const mgr = streamManagers.get(cameraId);
            if (mgr) {
                mgr.destroy();
                streamManagers.delete(cameraId);
            }
            manualPlayIds.delete(cameraId);
            cancelledTempIds.add(cameraId);
            resetCellToPlaceholder(cameraId);
        } else {
            manualPlayIds.add(cameraId);
        }
        refreshCellStates();
        return;
    }
    const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
    if (!cell) return;
    cancelledTempIds.delete(cameraId);
    manualPlayIds.add(cameraId);
    initStream(cell, camera, camera.target_url).catch(() => {});
    refreshCellStates();
}

   const ICON_SOUND_ON = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>`;
const ICON_SOUND_OFF = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><line x1="23" x2="17" y1="9" y2="15"/><line x1="17" x2="23" y1="9" y2="15"/></svg>`;

function updateSpeakerButton(cell, camera) {
    let btn = cell.querySelector(".cell-speak-toggle");
    if (!btn) {
        btn = document.createElement("button");
        btn.className = "cell-speak-toggle";
        btn.style.display = "none";
        btn.setAttribute("aria-label", "Nyalakan suara");
        const info = cell.querySelector(".camera-placeholder-info");
        if (info) info.appendChild(btn);
        else cell.appendChild(btn);
    }
    if (cell.classList.contains("fullscreen")) {
        btn.style.display = "none";
        btn.classList.remove("speaking");
        cell.classList.remove("has-live-control");
        return;
    }
    const mgr = streamManagers.get(camera.id);
    const show = camera.status === "online" && !!mgr && mgr.hasAudio;
    btn.style.display = show ? "" : "none";
    cell.classList.toggle("has-live-control", show);
    if (!show) {
        btn.classList.remove("speaking");
        return;
    }
    const speaking = !mgr.video.muted;
    btn.innerHTML = speaking ? ICON_SOUND_ON : ICON_SOUND_OFF;
    btn.setAttribute("aria-label", speaking ? "Bisukan" : "Nyalakan suara");
    btn.classList.toggle("speaking", speaking);
}

function refreshSpeakerButton(cameraId) {
    const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
    const camera = camerasMap.get(cameraId);
    if (cell && camera) updateSpeakerButton(cell, camera);
}

function toggleMute(cameraId) {
    const camera = camerasMap.get(cameraId);
    const mgr = streamManagers.get(cameraId);
    if (!camera || camera.status !== "online" || !mgr || !mgr.hasAudio) return;
    if (mgr.video && !mgr.video.muted) {
        setCellMuted(cameraId, true);
    } else {
        setCellMuted(cameraId, false);
        attemptPlay(mgr);
    }
    refreshCellStates();
}

const ICON_PAUSE = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>`;

let fsBar = null;
let fsBarVideo = null;
let fsScrubbing = false;
let fsPrev = null;
let fsNext = null;
let fsIdleTimer = null;
let fsShownAt = 0;

const ICON_CHEV_LEFT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>`;
const ICON_CHEV_RIGHT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>`;

function stepFullscreen(dir) {
    if (fullscreenCameraId === null) return;
    const visible = getVisibleCameras();
    if (visible.length <= 1) return;
    const idx = visible.findIndex((c) => c.id === fullscreenCameraId);
    if (idx === -1) return;
    switchFullscreen(visible[(idx + dir + visible.length) % visible.length].id);
}

function fsChromeHidden() {
    return document.body.classList.contains("fs-idle");
}

function showFsChrome() {
    const wasHidden = fsChromeHidden();
    document.body.classList.remove("fs-idle");
    clearTimeout(fsIdleTimer);
    if (wasHidden) fsShownAt = Date.now();
    if (fullscreenCameraId !== null) {
        fsIdleTimer = setTimeout(() => {
            document.body.classList.add("fs-idle");
        }, 3000);
    }
}

function fsSel(act) {
    return fsBar ? fsBar.querySelector(`[data-act="${act}"]`) : null;
}

function fsCurrentManager() {
    return fullscreenCameraId === null ? null : streamManagers.get(fullscreenCameraId) || null;
}

function setCellMuted(id, muted) {
    const mgr = streamManagers.get(id);
    if (!mgr) return;
    mgr.setMuted(muted);
    if (muted) mutedIds.add(id);
    else mutedIds.delete(id);
    refreshSpeakerButton(id);
}

function attemptPlay(mgr) {
    try {
        const played = mgr.video && mgr.video.play();
        if (played && typeof played.catch === "function") played.catch(() => {});
    } catch {
        /* older browser without play() promise */
    }
}

function toggleFsPlay() {
    const id = fullscreenCameraId;
    if (id === null) return;
    const mgr = streamManagers.get(id);
    if (!mgr) return;
    if (mgr.userPaused || (mgr.video && mgr.video.paused)) mgr.resumeUser();
    else mgr.pauseUser();
    updateFsBar();
    refreshCellStates();
}

function initFsBar() {
    if (fsBar) return;
    fsBar = document.createElement("div");
    fsBar.id = "fs-controls";
    fsBar.style.display = "none";
    fsBar.innerHTML = `
        <div class="fs-progress"><input data-act="seek" type="range" min="0" max="1" step="0.1" value="1" aria-label="Posisi tayangan"></div>
        <div class="fs-row">
        <button data-act="play" aria-label="Jeda"></button>
        <button data-act="back" aria-label="Mundur 10 detik">&minus;10s</button>
        <span class="fs-time" data-act="time" aria-label="Waktu tayangan"></span>
        <span class="fs-spacer"></span>
        <button data-act="live" aria-label="Kembali ke siaran langsung">\u25CF LIVE</button>
        <button data-act="mute" aria-label="Bisukan"></button>
        <input data-act="vol" type="range" min="0" max="100" value="100" aria-label="Volume">
        </div>`;
    fsBar.querySelector('[data-act="play"]').addEventListener("click", () => toggleFsPlay());
    fsBar.querySelector('[data-act="back"]').addEventListener("click", () => {
        const m = fsCurrentManager();
        if (m) {
            m.seekBy(-10);
            updateFsBar();
        }
    });
    fsBar.querySelector('[data-act="live"]').addEventListener("click", () => {
        const m = fsCurrentManager();
        if (m) {
            m.syncToLiveEdge();
            updateFsBar();
        }
    });
    fsBar.querySelector('[data-act="mute"]').addEventListener("click", () => {
        if (fullscreenCameraId !== null) {
            toggleMute(fullscreenCameraId);
            updateFsBar();
        }
    });
    const seekEl = fsBar.querySelector('[data-act="seek"]');
    seekEl.addEventListener("pointerdown", () => {
        fsScrubbing = true;
    });
    window.addEventListener("pointerup", () => {
        if (fsScrubbing) {
            fsScrubbing = false;
            updateFsBar();
        }
    });
    seekEl.addEventListener("input", () => {
        const m = fsCurrentManager();
        if (m) {
            m.seekTo(parseFloat(seekEl.value));
            updateFsBar();
        }
    });
    const volEl = fsBar.querySelector('[data-act="vol"]');
    volEl.addEventListener("input", () => {
        const id = fullscreenCameraId;
        const m = id === null ? null : streamManagers.get(id);
        if (!m || !m.video || id === null) return;
        const val = Math.max(0, Math.min(100, parseInt(volEl.value, 10) || 0)) / 100;
        m.video.volume = val;
        setCellMuted(id, val <= 0);
        if (val > 0) attemptPlay(m);
        updateFsBar();
    });
    document.body.appendChild(fsBar);
    fsPrev = document.createElement("button");
    fsPrev.className = "fs-nav fs-prev";
    fsPrev.setAttribute("aria-label", "Kamera sebelumnya");
    fsPrev.innerHTML = ICON_CHEV_LEFT;
    fsPrev.style.display = "none";
    fsPrev.addEventListener("click", () => stepFullscreen(-1));
    fsNext = document.createElement("button");
    fsNext.className = "fs-nav fs-next";
    fsNext.setAttribute("aria-label", "Kamera berikutnya");
    fsNext.innerHTML = ICON_CHEV_RIGHT;
    fsNext.style.display = "none";
    fsNext.addEventListener("click", () => stepFullscreen(1));
    document.body.appendChild(fsPrev);
    document.body.appendChild(fsNext);
}

function onFsBarTime() {
    updateFsBar();
}

function bindFsBar(cameraId) {
    if (fsBarVideo) fsBarVideo.removeEventListener("timeupdate", onFsBarTime);
    const mgr = streamManagers.get(cameraId);
    fsBarVideo = (mgr && mgr.video) || null;
    if (fsBarVideo) fsBarVideo.addEventListener("timeupdate", onFsBarTime);
    updateFsBar();
}

function unbindFsBar() {
    if (fsBarVideo) fsBarVideo.removeEventListener("timeupdate", onFsBarTime);
    fsBarVideo = null;
    updateFsBar();
}

function updateFsBar() {
    if (!fsBar) return;
    const id = fullscreenCameraId;
    const mgr = id === null ? null : streamManagers.get(id) || null;
    if (!mgr || !mgr.video) {
        fsBar.style.display = "none";
        if (fsPrev) fsPrev.style.display = "none";
        if (fsNext) fsNext.style.display = "none";
        clearTimeout(fsIdleTimer);
        document.body.classList.remove("fs-idle");
        return;
    }
    fsBar.style.display = "";
    const v = mgr.video;
    const paused = !!mgr.userPaused || v.paused;
    const playBtn = fsSel("play");
    playBtn.innerHTML = paused ? ICON_PLAY : ICON_PAUSE;
    playBtn.setAttribute("aria-label", paused ? "Lanjutkan" : "Jeda");
    const seek = fsSel("seek");
    const w = mgr.getDvrWindow();
    if (!w || !(w.end > w.start)) {
        seek.disabled = true;
    } else {
        seek.disabled = false;
        seek.min = String(w.start);
        seek.max = String(w.end);
        if (!fsScrubbing) seek.value = String(v.currentTime);
    }
    const behind = mgr.secondsBehindLive();
    const liveBtn = fsSel("live");
    if (behind > 4) {
        liveBtn.classList.add("behind");
        liveBtn.textContent = `\u2212${Math.round(behind)}s LIVE`;
    } else {
        liveBtn.classList.remove("behind");
        liveBtn.textContent = "\u25CF LIVE";
    }
    const muted = v.muted;
    const muteBtn = fsSel("mute");
    muteBtn.innerHTML = muted ? ICON_SOUND_OFF : ICON_SOUND_ON;
    muteBtn.setAttribute("aria-label", muted ? "Nyalakan suara" : "Bisukan");
    const vol = fsSel("vol");
    vol.value = String(Math.round((v.volume ?? 1) * 100));
    const timeEl = fsSel("time");
    if (timeEl) {
        const wall = new Date(Date.now() - behind * 1000);
        const hh = String(wall.getHours()).padStart(2, "0");
        const mm = String(wall.getMinutes()).padStart(2, "0");
        const ss = String(wall.getSeconds()).padStart(2, "0");
        timeEl.textContent = `${hh}:${mm}:${ss}`;
    }
    const navCount = getVisibleCameras().length;
    if (fsPrev) fsPrev.style.display = navCount > 1 ? "" : "none";
    if (fsNext) fsNext.style.display = navCount > 1 ? "" : "none";
}

function suspendOtherStreams(activeId) {
    streamManagers.forEach((manager, id) => {
        if (id !== activeId) manager.suspend();
    });
}

function resumeAllStreams() {
    streamManagers.forEach((manager) => manager.resume());
}

function getVisibleCameras() {
    return cameras.filter((c) => {
        const cell = document.querySelector(`.camera-cell[data-id="${c.id}"]`);
        return cell && cell.style.display !== "none" && c.status === "online";
    });
}

function switchFullscreen(newCameraId) {
    if (newCameraId === fullscreenCameraId) return;

    if (tempFullscreenId !== null && tempFullscreenId !== newCameraId) {
        cleanupTempFullscreenStream(tempFullscreenId);
    }

    const oldCell = document.querySelector(`.camera-cell[data-id="${fullscreenCameraId}"]`);
    if (oldCell) oldCell.classList.remove("fullscreen");

    const newCell = document.querySelector(`.camera-cell[data-id="${newCameraId}"]`);
    if (!newCell) {
        fullscreenCameraId = null;
        cleanupTempFullscreenStream();
        enforceAutoPlayCap();
        showNavbar();
        resumeAllStreams();
        return;
    }

    fullscreenCameraId = newCameraId;
    newCell.classList.add("fullscreen");
    newCell.focus();

    clearTimeout(navbarTimeout);
    scheduleNavbarHide();

    ensureTempFullscreenStream(newCameraId);
    bindFsBar(newCameraId);

    showFsChrome();
    streamManagers.forEach((mgr, id) => {
        if (id !== newCameraId) mgr.suspend();
    });

    refreshCellStates();

    const camera = camerasMap.get(newCameraId);
    announce(`${camera?.name || ""} - fullscreen view`);
}

function enterFullscreen(cameraId) {
    const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
    if (!cell) return;
    fullscreenCameraId = cameraId;
    cell.classList.add("fullscreen");
    cell.focus();
    suspendOtherStreams(cameraId);
    ensureTempFullscreenStream(cameraId);
    bindFsBar(cameraId);
    showFsChrome();
    clearTimeout(navbarTimeout);
    navbar.classList.add("hidden");
    const camera = camerasMap.get(cameraId);
    const displayName = camera?.name || "";
    announce(`${displayName} - fullscreen view`);
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.()?.catch(() => {});
    }
}

function exitFullscreen() {
    if (fullscreenCameraId === null) return;
    const cell = document.querySelector(
        `.camera-cell[data-id="${fullscreenCameraId}"]`,
    );
    if (cell) cell.classList.remove("fullscreen");
    fullscreenCameraId = null;
    cleanupTempFullscreenStream();
    enforceAutoPlayCap();
    unbindFsBar();
    showNavbar();
    resumeAllStreams();
    announce("Exited fullscreen view");
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    }
}

function enterGridFullscreen() {
    gridFullscreen = true;
    grid.classList.add("grid-fullscreen");
    document.body.classList.add("grid-fs");
    showNavbar();
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.()?.catch(() => {});
    }
    announce("Fullscreen grid view");
}

function exitGridFullscreen() {
    gridFullscreen = false;
    grid.classList.remove("grid-fullscreen");
    document.body.classList.remove("grid-fs");
    showNavbar();
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    }
    announce("Exited fullscreen grid view");
}

function handleFullscreenChange() {
    if (document.fullscreenElement) {
        const cell = document.fullscreenElement.closest(".camera-cell");
        if (!cell) return;
        const id = parseInt(cell.dataset.id, 10);
        fullscreenCameraId = id;
        cell.classList.add("fullscreen");
        cell.focus();
        clearTimeout(navbarTimeout);
        if (window.innerWidth > 768) {
            navbar.classList.add("hidden");
        }
        suspendOtherStreams(id);
        ensureTempFullscreenStream(id);
        bindFsBar(id);
        const camera = camerasMap.get(id);
        const displayName = camera?.name || "";
        announce(`${displayName} - fullscreen view`);
    } else {
if (fullscreenCameraId !== null) {
            exitFullscreen();
            return;
        }
        if (gridFullscreen) {
            gridFullscreen = false;
            grid.classList.remove("grid-fullscreen");
            document.body.classList.remove("grid-fs");
        }
        showNavbar();
        resumeAllStreams();
    }
}

document.addEventListener("fullscreenchange", handleFullscreenChange);
document.addEventListener("webkitfullscreenchange", handleFullscreenChange);

function scheduleNavbarHide() {
    clearTimeout(navbarTimeout);
    const delay =
        fullscreenCameraId !== null
            ? NAVBAR_HIDE_DELAY
            : NAVBAR_HIDE_DELAY_GRID;
    navbarTimeout = setTimeout(() => {
        navbar.classList.add("hidden");
    }, delay);
}

function showNavbar() {
    navbar.classList.remove("hidden");
    clearTimeout(navbarTimeout);
    scheduleNavbarHide();
}

function announce(message) {
    const el = document.getElementById("announcements");
    if (el) {
        el.textContent = "";
        requestAnimationFrame(() => {
            el.textContent = message;
        });
    }
}

function loadSelection() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_SELECTION);
        if (stored) {
            const arr = JSON.parse(stored);
            if (Array.isArray(arr) && arr.length > 0) {
                cameraSelection = new Set(arr);
                return;
            }
        }
    } catch {}
    cameraSelection = null;
}

function saveSelection() {
    try {
        if (cameraSelection === null || cameraSelection.size === cameras.length) {
            localStorage.removeItem(STORAGE_KEY_SELECTION);
        } else {
            localStorage.setItem(STORAGE_KEY_SELECTION, JSON.stringify([...cameraSelection]));
        }
    } catch {}
}

function initSelectionPanel() {
    const overlay = document.createElement("div");
    overlay.className = "selection-overlay";
    overlay.setAttribute("aria-hidden", "true");

    const panel = document.createElement("aside");
    panel.className = "selection-panel";
    panel.setAttribute("role", "dialog");
    panel.setAttribute("aria-label", "Camera selection");

    panel.innerHTML = `
      <div class="selection-panel-header">
        <h2>Select Cameras</h2>
        <button class="selection-panel-close" aria-label="Close selection panel">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
      <div class="selection-panel-actions">
        <button class="selection-action-btn" data-action="select-all">Select All</button>
        <button class="selection-action-btn" data-action="deselect-all">Deselect All</button>
      </div>
      <div class="selection-panel-list"></div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(panel);

    const list = panel.querySelector(".selection-panel-list");
    const closeBtn = panel.querySelector(".selection-panel-close");
    const selectAllBtn = panel.querySelector('[data-action="select-all"]');
    const deselectAllBtn = panel.querySelector('[data-action="deselect-all"]');

    function renderList() {
        list.innerHTML = "";
        cameras.filter((c) => c.status === "online").forEach((c) => {
            const selected = cameraSelection === null || cameraSelection.has(c.id);
            const item = document.createElement("label");
            item.className = "selection-item";
            const catColor = categoryColor(c.category);
            item.innerHTML = `
              <span class="toggle-switch">
                <input type="checkbox" ${selected ? "checked" : ""} aria-label="Show ${c.name}">
                <span class="toggle-slider"></span>
              </span>
              <span class="selection-item-name">${escapeHtml(c.name)}</span>
              <span class="selection-item-category" style="background:${catColor}22;color:${catColor}">${c.category}</span>
              <span class="selection-item-status ${c.status}">${c.status}</span>
            `;
            item.querySelector("input").addEventListener("change", (e) => {
                if (cameraSelection === null) {
                    cameraSelection = new Set(cameras.map((x) => x.id));
                }
                if (e.target.checked) {
                    cameraSelection.add(c.id);
                } else {
                    cameraSelection.delete(c.id);
                }
                saveSelection();
                applyFilters();
            });
            list.appendChild(item);
        });
    }

    function open() {
        renderList();
        overlay.classList.add("open");
        panel.classList.add("open");
        overlay.removeAttribute("aria-hidden");
    }

    function close() {
        overlay.classList.remove("open");
        panel.classList.remove("open");
        overlay.setAttribute("aria-hidden", "true");
    }

    closeBtn.addEventListener("click", close);
    overlay.addEventListener("click", close);

    selectAllBtn.addEventListener("click", () => {
        cameraSelection = null;
        saveSelection();
        renderList();
        applyFilters();
    });

    deselectAllBtn.addEventListener("click", () => {
        cameraSelection = new Set();
        saveSelection();
        renderList();
        applyFilters();
    });

    document.getElementById("select-btn")?.addEventListener("click", open);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && panel.classList.contains("open")) {
            close();
        }
    });
}

function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

function throttle(fn, limit) {
    let inThrottle = false;
    return (...args) => {
        if (!inThrottle) {
            fn(...args);
            inThrottle = true;
            setTimeout(() => {
                inThrottle = false;
            }, limit);
        }
    };
}

searchInput?.addEventListener(
    "input",
    debounce((e) => {
        searchQuery = e.target.value;
        applyFilters();
    }, 150),
);

function getCategoryDropdowns() {
    return [...document.querySelectorAll("[data-cat-dropdown]")];
}

function syncCategoryDropdowns() {
    const all = allCategorySlugs();
    const full = all.length > 0 && all.every((s) => selectedCategories.has(s));
    const label = full || selectedCategories.size === 0 ? "Semua" : `Kategori (${selectedCategories.size})`;
    getCategoryDropdowns().forEach((drop) => {
        const effective = selectedCategories.size === 0 ? new Set(all) : selectedCategories;
        drop.querySelectorAll('input[type="checkbox"][data-slug]').forEach((box) => {
            box.checked = effective.has(box.value);
        });
        const lab = drop.querySelector("[data-cat-label]");
        if (lab) lab.textContent = label;
        const btn = drop.querySelector("[data-cat-button]");
        if (btn) btn.setAttribute("aria-expanded", drop.classList.contains("open") ? "true" : "false");
    });
}

function setCategoryDropdownOpen(drop, open) {
    drop.classList.toggle("open", open);
    const btn = drop.querySelector("[data-cat-button]");
    if (btn) btn.setAttribute("aria-expanded", open ? "true" : "false");
}

function closeAllCategoryDropdowns(except = null) {
    getCategoryDropdowns().forEach((d) => {
        if (d !== except) setCategoryDropdownOpen(d, false);
    });
}

getCategoryDropdowns().forEach((drop) => {
    drop.querySelector("[data-cat-button]")?.addEventListener("click", (e) => {
        e.stopPropagation();
        const willOpen = !drop.classList.contains("open");
        closeAllCategoryDropdowns(drop);
        setCategoryDropdownOpen(drop, willOpen);
    });
    drop.querySelector("[data-cat-reset]")?.addEventListener("click", () => {
        selectedCategories = new Set();
        persistCategorySet();
        syncCategoryDropdowns();
        applyFilters();
    });
    drop.addEventListener("change", (e) => {
        const box = e.target.closest('input[type="checkbox"][data-slug]');
        if (!box || !drop.contains(box)) return;
        const checked = [...drop.querySelectorAll('input[type="checkbox"][data-slug]:checked')].map(
            (b) => b.value,
        );
        selectedCategories = new Set(checked);
        persistCategorySet();
        syncCategoryDropdowns();
        applyFilters();
    });
});
document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-cat-dropdown]")) closeAllCategoryDropdowns();
});
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeAllCategoryDropdowns();
});
syncCategoryDropdowns();

statusFilter?.addEventListener("change", (e) => {
    selectedStatus = e.target.value;
    applyFilters();
});

 grid?.addEventListener("click", (e) => {
    const closeBtn = e.target.closest(".fullscreen-close");
    if (closeBtn) {
        exitFullscreen();
        return;
    }
    const playToggle = e.target.closest(".cell-play-btn, .cell-pause-btn, .cell-speak-toggle");
    if (playToggle) {
        if (e.target.closest(".cell-speak-toggle")) {
            const speakCell = e.target.closest(".camera-cell");
            if (speakCell) toggleMute(parseInt(speakCell.dataset.id, 10));
            return;
        }
        const toggleCell = e.target.closest(".camera-cell");
        if (toggleCell) toggleManualPlay(parseInt(toggleCell.dataset.id, 10));
        return;
    }
    const cell = e.target.closest(".camera-cell");
    if (!cell) return;
    if (fullscreenCameraId === null) {
        enterFullscreen(parseInt(cell.dataset.id, 10));
    } else if (fsChromeHidden() || Date.now() - fsShownAt < 600) {
        showFsChrome();
    } else {
        exitFullscreen();
    }
});

grid?.addEventListener("stream-audio", (e) => {
    const id = e.detail?.cameraId;
    if (id === undefined || id === null) return;
    const mgr = streamManagers.get(id);
    if (mgr && mgr.hasAudio && !mutedIds.has(id) && mgr.video && !mgr.video.paused && mgr.video.muted) {
        mgr.setMuted(false);
    }
    refreshSpeakerButton(id);
});

grid?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
        const closeBtn = e.target.closest(".fullscreen-close");
        if (closeBtn) return;
        if (e.target.closest(".cell-play-btn, .cell-pause-btn, .cell-speak-toggle")) return;
        e.preventDefault();
        const cell = e.target.closest(".camera-cell");
        if (!cell) return;
        if (fullscreenCameraId === null) {
            enterFullscreen(parseInt(cell.dataset.id, 10));
        } else {
            exitFullscreen();
        }
    }
});

function toggleFullscreen() {
    if (gridFullscreen) {
        exitGridFullscreen();
    } else if (fullscreenCameraId !== null) {
        exitFullscreen();
    } else {
        enterGridFullscreen();
    }
}

document.addEventListener("keydown", (e) => {
    const tag = e.target.tagName;
    const isInput = tag === "INPUT" || tag === "SELECT" || tag === "TEXTAREA";

    if (e.key === "Escape" && (fullscreenCameraId !== null || gridFullscreen)) {
        if (gridFullscreen) {
            exitGridFullscreen();
        } else {
            exitFullscreen();
        }
        return;
    }

    if (fullscreenCameraId !== null && (e.key === "ArrowLeft" || e.key === "ArrowRight" || e.key === "ArrowUp" || e.key === "ArrowDown")) {
        e.preventDefault();
        const visible = getVisibleCameras();
        if (visible.length <= 1) return;
        const idx = visible.findIndex((c) => c.id === fullscreenCameraId);
        if (idx === -1) return;
        if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
            switchFullscreen(visible[(idx - 1 + visible.length) % visible.length].id);
        } else {
            switchFullscreen(visible[(idx + 1) % visible.length].id);
        }
        return;
    }

    if (gridFullscreen && !isInput && (e.key === "ArrowLeft" || e.key === "ArrowRight" || e.key === "ArrowUp" || e.key === "ArrowDown")) {
        e.preventDefault();
        const visible = getVisibleCameras();
        if (visible.length === 0) return;
        if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
            enterFullscreen(visible[visible.length - 1].id);
        } else {
            enterFullscreen(visible[0].id);
        }
        return;
    }

    if (fullscreenCameraId !== null && !isInput && (e.key === " " || e.key === "m" || e.key === "M")) {
        const t = e.target;
        if (t && (t.tagName === "BUTTON" || t.tagName === "INPUT" || t.tagName === "SELECT" || t.tagName === "TEXTAREA")) return;
        e.preventDefault();
        if (e.key === " ") toggleFsPlay();
        else toggleMute(fullscreenCameraId);
        return;
    }

    if (e.key === "F11") {
        e.preventDefault();
        toggleFullscreen();
        return;
    }

    if (!isInput && (e.key === "f" || e.key === "F")) {
        e.preventDefault();
        toggleFullscreen();
        return;
    }

    if (!isInput && (e.key === "r" || e.key === "R")) {
        pollLocalJson();
        return;
    }
});

function initFilterSheet() {
    const toggleBtn = document.getElementById("filter-toggle");
    const sheet = document.getElementById("filter-sheet");
    const overlay = document.getElementById("filter-sheet-overlay");
    const closeBtn = document.getElementById("filter-sheet-close");
    const statusSelect = document.getElementById("status-filter-sheet");
    const refreshBtn = document.getElementById("refresh-btn-sheet");
    const selectBtn = document.getElementById("select-btn-sheet");

    function openSheet() {
        syncCategoryDropdowns();
        if (statusSelect) statusSelect.value = selectedStatus;
        sheet?.classList.add("open");
        overlay?.classList.add("open");
        document.body.style.overflow = "hidden";
    }

    function closeSheet() {
        sheet?.classList.remove("open");
        overlay?.classList.remove("open");
        document.body.style.overflow = "";
    }

    toggleBtn?.addEventListener("click", openSheet);
    closeBtn?.addEventListener("click", closeSheet);
    overlay?.addEventListener("click", closeSheet);

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && sheet?.classList.contains("open")) {
            closeSheet();
        }
    });

    statusSelect?.addEventListener("change", function () {
        const mainStatus = document.getElementById("status-filter");
        if (mainStatus) mainStatus.value = this.value;
        selectedStatus = this.value;
        closeSheet();
        applyFilters();
    });

     refreshBtn?.addEventListener("click", function () {
        closeSheet();
        pollLocalJson();
    });

    selectBtn?.addEventListener("click", function () {
        closeSheet();
        document.getElementById("select-btn")?.click();
    });

    const CHIP_OFF = ["chip-off"];
    const CHIP_ON = ["chip-on"];

    function toTitleCase(s) {
        return String(s)
            .toLowerCase()
            .split(" ")
            .map((w) => (w ? w.charAt(0).toUpperCase() + w.slice(1) : w))
            .join(" ");
    }

    function syncPanelChips() {
        document.querySelectorAll('[data-chip-group="status"] .chip-m').forEach((b) => {
            const on = (b.dataset.value || "") === (selectedStatus || "");
            b.classList.remove(...(on ? CHIP_OFF : CHIP_ON));
            b.classList.add(...(on ? CHIP_ON : CHIP_OFF));
        });
        const allSlugs = allCategorySlugs();
        const effectiveCats =
            selectedCategories.size === 0 ? new Set(allSlugs) : selectedCategories;
        const allOn = allSlugs.length > 0 && allSlugs.every((s) => effectiveCats.has(s));
        document.querySelectorAll('[data-chip-group="category"] .chip-m').forEach((b) => {
            const v = b.dataset.value || "";
            const on = v === "" ? allOn : effectiveCats.has(v);
            b.classList.remove(...(on ? CHIP_OFF : CHIP_ON));
            b.classList.add(...(on ? CHIP_ON : CHIP_OFF));
        });
    }

    document.querySelectorAll("[data-chip-group] .chip-m").forEach((b) => {
        b.addEventListener("click", () => {
            const group = b.closest("[data-chip-group]").dataset.chipGroup;
            const v = b.dataset.value || "";
            if (group === "status") {
                selectedStatus = v;
                if (statusSelect) statusSelect.value = v;
            } else if (v === "") {
                selectedCategories = new Set();
                persistCategorySet();
                syncCategoryDropdowns();
            } else {
                const effective =
                    selectedCategories.size === 0
                        ? new Set(allCategorySlugs())
                        : new Set(selectedCategories);
                if (effective.has(v)) {
                    effective.delete(v);
                } else {
                    effective.add(v);
                }
                selectedCategories = effective;
                persistCategorySet();
                syncCategoryDropdowns();
            }
            applyFilters();
            syncPanelChips();
        });
    });

    function renderPanelCameraList() {
        const list = document.getElementById("panel-camera-list");
        if (!list) return;
        list.innerHTML = "";
        cameras
            .filter((c) => c.status === "online")
            .forEach((c) => {
                const selected = cameraSelection === null || cameraSelection.has(c.id);
                const li = document.createElement("li");
                li.className = "panel-camera-item";
                const name = document.createElement("span");
                name.className = "truncate pr-3";
                name.textContent = toTitleCase(c.name);
                const toggle = document.createElement("button");
                toggle.type = "button";
                toggle.className = "panel-toggle";
                toggle.setAttribute("role", "switch");
                toggle.setAttribute("aria-checked", String(selected));
                toggle.setAttribute("aria-label", "Tampilkan " + c.name);
                toggle.addEventListener("click", () => {
                    if (cameraSelection === null) {
                        cameraSelection = new Set(cameras.map((x) => x.id));
                    }
                    const on = toggle.getAttribute("aria-checked") !== "true";
                    if (on) {
                        cameraSelection.add(c.id);
                    } else {
                        cameraSelection.delete(c.id);
                    }
                    saveSelection();
                    applyFilters();
                    toggle.setAttribute("aria-checked", String(on));
                });
                li.appendChild(name);
                li.appendChild(toggle);
                list.appendChild(li);
            });
    }

    function valleyPathFor(w, h, r, hw, hh) {
        const cx = w / 2;
        const sx = hw / 56;
        const sy = hh / 28;
        const X = (n) => Math.round(n * sx * 100) / 100;
        const Y = (n) => Math.round(n * sy * 100) / 100;
        const f = (n) => Math.round(n * 100) / 100;
        return (
            `M${f(r)},0 H${f(w - r)} Q${f(w)},0 ${f(w)},${f(r)} ` +
            `V${f(h)} H${f(cx + hw)} ` +
            `C${f(cx + X(43))},${f(h)} ${f(cx + X(36))},${f(h)} ${f(cx + X(30))},${f(h - Y(9))} ` +
            `C${f(cx + X(23))},${f(h - Y(19))} ${f(cx + X(15))},${f(h - Y(28))} ${f(cx)},${f(h - Y(28))} ` +
            `C${f(cx - X(15))},${f(h - Y(28))} ${f(cx - X(23))},${f(h - Y(19))} ${f(cx - X(30))},${f(h - Y(9))} ` +
            `C${f(cx - X(36))},${f(h)} ${f(cx - X(43))},${f(h)} ${f(cx - hw)},${f(h)} ` +
            `H${f(r)} Q0,${f(h)} 0,${f(h - r)} V${f(r)} Q0,0 ${f(r)},0 Z`
        );
    }

    function layoutValleys() {
        const hillEl = document.querySelector(".dock-hill");
        const hillHW = hillEl && hillEl.offsetWidth ? Math.round(hillEl.offsetWidth / 2) : 72;
        const hillHH =
            hillEl && hillEl.offsetHeight
                ? Math.max(16, Math.round((hillEl.offsetHeight * 6) / 7))
                : 24;
        const jobs = [
            ["filter-sheet", "valleyPath", 32, hillHW, hillHH],
            ["shortcuts-popup", "shortcutsPath", 24, hillHW, hillHH],
            ["camera-popup", "cameraPath", 24, hillHW, hillHH],
        ];
        jobs.forEach(([elId, pathId, r, hw, hh]) => {
            const el = document.getElementById(elId);
            const path = document.getElementById(pathId);
            if (!el || !path) return;
            const w = el.offsetWidth;
            const h = el.offsetHeight;
            if (w && h) path.setAttribute("d", valleyPathFor(w, h, r, hw, hh));
        });
    }

    const searchPopup = document.getElementById("search-popup");
    searchPopup?.addEventListener("input", function () {
        const mainSearch = document.getElementById("search");
        if (mainSearch && mainSearch.value !== this.value) {
            mainSearch.value = this.value;
            mainSearch.dispatchEvent(new Event("input", { bubbles: true }));
        }
    });

    const dockBtn = document.getElementById("dock-menu-btn");
    function syncDockBtn() {
        const open = !!sheet?.classList.contains("open");
        dockBtn?.setAttribute("aria-expanded", String(open));
        dockBtn?.setAttribute("aria-label", open ? "Tutup menu" : "Buka menu");
        dockBtn?.classList.toggle("active", open);
    }

    const shortcutsPopup = document.getElementById("shortcuts-popup");
    const cameraPopup = document.getElementById("camera-popup");
    let isShortcutsOpen = false;
    let isCameraOpen = false;

    function setPopup(el, open) {
        if (!el) return;
        el.classList.toggle("open", open);
    }

    function replayStagger() {
        const rows = document.querySelectorAll("#panel-camera-list li");
        rows.forEach((li, i) => {
            li.classList.remove("in");
            li.style.transitionDelay = Math.min(i * 40, 320) + "ms";
        });
        requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                rows.forEach((li) => li.classList.add("in"));
            }),
        );
    }

    function setShortcuts(open) {
        isShortcutsOpen = open;
        setPopup(shortcutsPopup, open);
        if (open) {
            if (sheet?.classList.contains("open")) closeSheet();
            if (isCameraOpen) setCamera(false);
        }
        layoutValleys();
    }

    function setCamera(open) {
        isCameraOpen = open;
        setPopup(cameraPopup, open);
        if (open) {
            if (sheet?.classList.contains("open")) closeSheet();
            if (isShortcutsOpen) setShortcuts(false);
            renderPanelCameraList();
            replayStagger();
        }
        layoutValleys();
    }

    const _openSheet = openSheet;
    openSheet = function () {
        syncCategoryDropdowns();
        if (statusSelect) statusSelect.value = selectedStatus;
        syncPanelChips();
        _openSheet();
        syncDockBtn();
        if (isShortcutsOpen) setShortcuts(false);
        if (isCameraOpen) setCamera(false);
        layoutValleys();
    };

    const _closeSheet = closeSheet;
    closeSheet = function () {
        _closeSheet();
        syncDockBtn();
    };

    dockBtn?.addEventListener("click", function () {
        if (sheet?.classList.contains("open")) {
            closeSheet();
        } else {
            openSheet();
        }
    });

    document.getElementById("dock-camera-btn")?.addEventListener("click", function () {
        setCamera(!isCameraOpen);
    });

    document.getElementById("dock-info-btn")?.addEventListener("click", function () {
        setShortcuts(!isShortcutsOpen);
    });

    document.getElementById("shortcuts-close")?.addEventListener("click", function () {
        setShortcuts(false);
    });

    document.getElementById("camera-popup-close")?.addEventListener("click", function () {
        setCamera(false);
    });

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        if (sheet?.classList.contains("open")) closeSheet();
        if (isShortcutsOpen) setShortcuts(false);
        if (isCameraOpen) setCamera(false);
    });

    window.addEventListener("resize", layoutValleys);
}

navbar?.addEventListener("mouseenter", showNavbar);
document.addEventListener("mousemove", throttle(showNavbar, 100));
document.addEventListener("pointermove", throttle(() => {
    if (fullscreenCameraId !== null) showFsChrome();
}, 500));
document.addEventListener("touchstart", () => {
    if (fullscreenCameraId !== null) showFsChrome();
}, { passive: true });
document.addEventListener("touchstart", showNavbar);
searchInput?.addEventListener("focus", () => {
    clearTimeout(navbarTimeout);
});
searchInput?.addEventListener("blur", scheduleNavbarHide);

statusFilter?.addEventListener("focus", () => {
    clearTimeout(navbarTimeout);
});
statusFilter?.addEventListener("blur", scheduleNavbarHide);

window.addEventListener("resize", debounce(() => {
    applyFilters();
}, 150));

window.addEventListener("beforeunload", () => {
    cleanupStreamManagers();
    telemetry.destroy();
    if (observer) observer.disconnect();
});

document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
        streamManagers.forEach((manager) => manager.suspend());
    } else {
        streamManagers.forEach((manager, id) => {
            const camera = camerasMap.get(id);
            if (!camera || camera.status !== "online") return;
            const cell = document.querySelector(
                `.camera-cell[data-id="${id}"]`,
            );
            if (cell && cell.style.display === "none") return;
            manager.resume();
        });
    }
});

function initInfoModal() {
    const overlay = document.createElement("div");
    overlay.id = "info-overlay";
    overlay.className = "selection-overlay";
    overlay.setAttribute("aria-hidden", "true");

    const panel = document.createElement("aside");
    panel.id = "info-panel";
    panel.className = "selection-panel";
    panel.setAttribute("role", "dialog");
    panel.setAttribute("aria-modal", "true");
    panel.setAttribute("aria-label", "Keyboard shortcuts");

    const header = document.createElement("div");
    header.className = "selection-panel-header";
    const title = document.createElement("h2");
    title.textContent = "Keyboard Shortcuts";
    const closeBtn = document.createElement("button");
    closeBtn.className = "selection-panel-close";
    closeBtn.setAttribute("aria-label", "Close keyboard shortcuts");
    closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
    header.append(title, closeBtn);

    const list = document.createElement("div");
    list.className = "selection-panel-list";

    const shortcuts = [
        { keys: "F", label: "Fullscreen semua grid" },
        { keys: "R", label: "Refresh data kamera" },
        { keys: "Esc", label: "Keluar fullscreen / tutup panel" },
        { keys: "← → ↑ ↓", label: "Navigasi antar kamera (saat fullscreen satu tayangan)" },
        { keys: "F11", label: "Fullscreen browser" },
    ];

    shortcuts.forEach(({ keys, label }) => {
        const item = document.createElement("div");
        item.className = "info-item";
        const kbd = document.createElement("kbd");
        kbd.textContent = keys;
        const text = document.createElement("span");
        text.textContent = label;
        item.append(kbd, text);
        list.appendChild(item);
    });

    panel.append(header, list);
    overlay.appendChild(panel);
    document.body.appendChild(overlay);

    const open = () => {
        overlay.classList.add("open");
        panel.classList.add("open");
        overlay.setAttribute("aria-hidden", "false");
        closeBtn.focus();
    };
    const close = () => {
        overlay.classList.remove("open");
        panel.classList.remove("open");
        overlay.setAttribute("aria-hidden", "true");
    };

    document.getElementById("info-btn")?.addEventListener("click", open);
    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) close();
    });
    closeBtn.addEventListener("click", close);
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && overlay.classList.contains("open")) {
            close();
        }
    });
}

function initPage() {
    cameras.forEach((c) => {
        currentCameraStates[c.id] = c.status;
    });

    document.querySelectorAll(".camera-cell").forEach((cell) => {
        if (cell.dataset.status === "online") {
            cell.style.order = "-1";
        }
    });

    document.getElementById("refresh-btn")?.addEventListener("click", () => {
        pollLocalJson();
    });

    telemetry.init();
    loadSelection();
    initFilterSheet();
    initSelectionPanel();
    initInfoModal();
    initObserver();
    initPatrolToast();
    applyFilters();
    initStaggeredBurst();
    initFsBar();
    showNavbar();
    setInterval(pollLocalJson, 8000);
}

initPage();

