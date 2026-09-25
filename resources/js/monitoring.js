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
let selectedCategory = "";
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
                (c) => c.category === "patroli"
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
);
let _activeStreamInit = 0;
let _streamQueue = [];

const grid = document.getElementById("camera-grid");
const cameraNames = new Map(cameras.map((c) => [c.id, c.name]));
const searchInput = document.getElementById("search");
const categoryFilter = document.getElementById("category-filter");
const statusFilter = document.getElementById("status-filter");
const liveCounter = document.getElementById("live-counter");
const manualPlayIds = new Set();
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

    if (selectedCategory) {
        filtered = filtered.filter((c) => c.category === selectedCategory);
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
        badge.textContent = `${displayName} - ${status}`;
    }
    cell.setAttribute("aria-label", `${displayName} - ${status}`);
}

function cameraPriority(c) {
    return c.status === "online" ? 0 : 1;
}

function getMaxAutoPlayRaw() {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    return vw > 768
        ? MAX_AUTO_PLAY_DESKTOP
        : vw > vh
          ? MAX_AUTO_PLAY_MOBILE_LANDSCAPE
          : MAX_AUTO_PLAY_MOBILE_PORTRAIT;
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
                (camera.category || "").toLowerCase() === "patroli";

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
          <button class="cell-play-btn" style="display:none" aria-label="Putar tayangan"></button>
          <span class="placeholder-icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M10 9l5 3-5 3V9z"/>
          </svg>
          </span>
          <span class="placeholder-text">Loading stream...</span>
          <span class="placeholder-caption">${escapeHtml(camera.name || "")}</span>
        </div>
        <video muted autoplay playsinline></video>
        <button class="cell-pause-btn" style="display:none" aria-label="Berhenti memutar"></button>
        <div class="camera-placeholder-info">
          <span class="status-badge ${escapeHtml(camera.status || "offline")}">${escapeHtml(camera.name || "")} - ${escapeHtml(camera.status || "")}</span>
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
    const targetAspect = 16 / 9;
    const isMobile = vw <= 768;
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

const ICON_PLAY = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>`;
const ICON_STOP = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>`;

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
    if (showPlay) playBtn.innerHTML = ICON_PLAY;
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
    clearTimeout(navbarTimeout);
    navbar.classList.add("hidden");
    const camera = camerasMap.get(cameraId);
    const displayName = camera?.name || "";
    announce(`${displayName} - fullscreen view`);
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
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
        document.documentElement.requestFullscreen().catch(() => {});
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
    if (window.innerWidth <= 768) return;
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

categoryFilter?.addEventListener("change", (e) => {
    selectedCategory = e.target.value;
    applyFilters();
});

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
    const playToggle = e.target.closest(".cell-play-btn, .cell-pause-btn");
    if (playToggle) {
        const toggleCell = e.target.closest(".camera-cell");
        if (toggleCell) toggleManualPlay(parseInt(toggleCell.dataset.id, 10));
        return;
    }
    const cell = e.target.closest(".camera-cell");
    if (!cell) return;
    if (fullscreenCameraId === null) {
        enterFullscreen(parseInt(cell.dataset.id, 10));
    } else {
        exitFullscreen();
    }
});

grid?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
        const closeBtn = e.target.closest(".fullscreen-close");
        if (closeBtn) return;
        if (e.target.closest(".cell-play-btn, .cell-pause-btn")) return;
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
    const catSelect = document.getElementById("category-filter-sheet");
    const statusSelect = document.getElementById("status-filter-sheet");
    const refreshBtn = document.getElementById("refresh-btn-sheet");
    const selectBtn = document.getElementById("select-btn-sheet");

    function openSheet() {
        if (catSelect) catSelect.value = selectedCategory;
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

    catSelect?.addEventListener("change", function () {
        const mainCat = document.getElementById("category-filter");
        if (mainCat) mainCat.value = this.value;
        selectedCategory = this.value;
        closeSheet();
        applyFilters();
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
}

navbar?.addEventListener("mouseenter", showNavbar);
document.addEventListener("mousemove", throttle(showNavbar, 100));
document.addEventListener("touchstart", showNavbar);
searchInput?.addEventListener("focus", () => {
    clearTimeout(navbarTimeout);
});
searchInput?.addEventListener("blur", scheduleNavbarHide);

categoryFilter?.addEventListener("focus", () => {
    clearTimeout(navbarTimeout);
});
categoryFilter?.addEventListener("blur", scheduleNavbarHide);

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
    initStaggeredBurst();
    showNavbar();
    setInterval(pollLocalJson, 8000);
}

initPage();
