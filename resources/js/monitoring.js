const DATA_SCRIPT = document.getElementById("monitoring-data");
const CAMERAS = DATA_SCRIPT ? JSON.parse(DATA_SCRIPT.textContent) : [];

const NAVBAR_HIDE_DELAY = 2000;
const NAVBAR_HIDE_DELAY_GRID = 2000;
const TELEMETRY_ENDPOINT = "/api/telemetry";
const TELEMETRY_FLUSH_INTERVAL = 300000;
const TELEMETRY_FLUSH_THRESHOLD = 20;
const STORAGE_KEY_SELECTION = "camera-selection";

let cameras = CAMERAS;
let currentCameraStates = {};
let searchQuery = "";
let selectedCategory = "";
let selectedStatus = "online";
let fullscreenCameraId = null;
let cameraSelection = null;
let navbarTimeout = null;
let streamManagers = new Map();
let initQueue = new Map();
let observer = null;
let _polling = false;

const grid = document.getElementById("camera-grid");
const cameraNames = new Map(cameras.map((c) => [c.id, c.name]));
const searchInput = document.getElementById("search");
const categoryFilter = document.getElementById("category-filter");
const statusFilter = document.getElementById("status-filter");
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
    const camera = cameras.find((c) => c.id === camId);
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

function buildGrid() {
    if (grid.querySelectorAll(".camera-cell").length > 0) {
        cleanupStreamManagers();
        initObserver();
        return;
    }

    const fragment = document.createDocumentFragment();

    const sorted = [...cameras].sort(
        (a, b) => cameraPriority(a) - cameraPriority(b),
    );

    sorted.forEach((c) => {
        const cell = document.createElement("div");
        cell.className = "camera-cell";
        cell.dataset.id = c.id;
        cell.dataset.name = c.name.toLowerCase();
        cell.dataset.category = c.category;
        cell.dataset.status = c.status;
        cell.setAttribute("tabindex", "0");
        cell.setAttribute("role", "button");
        cell.setAttribute("aria-label", `${c.name} - ${c.status}`);

        cell.innerHTML = `
      <div class="camera-placeholder">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="M10 9l5 3-5 3V9z"/>
        </svg>
        <span class="placeholder-text">Loading stream...</span>
      </div>
      <video muted autoplay playsinline></video>
      <div class="camera-placeholder-info">
        <span class="status-badge ${escapeHtml(c.status)}">${escapeHtml(c.name)} - ${escapeHtml(c.status)}</span>
      </div>
      <button class="fullscreen-close" aria-label="Exit fullscreen">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
        </svg>
      </button>
    `;

        if (c.status === "online") {
            cell.style.order = "-1";
        }

        fragment.appendChild(cell);
    });

    const existingNavbar = grid.querySelector("#navbar");
    grid.replaceChildren(fragment);
    if (existingNavbar) {
        grid.insertBefore(existingNavbar, grid.firstChild);
    }
    cleanupStreamManagers();
    initObserver();
}

function onStreamEnded(cameraId) {
    const camera = cameras.find((c) => c.id === cameraId);
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
    cell.querySelector("video").style.display = "none";
    updateBadge(cell, "offline");
}

async function initStream(cell, camera, targetUrl) {
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

    try {
        const stagger = (camera.id * 137.5 + Math.random() * 500) % 2000;

        setTimeout(async () => {
            try {
                const stillOnline = camera.status === "online";
                if (!stillOnline || cell.style.display === "none") {
                    _unlock();
                    return;
                }

                const collision = streamManagers.get(camera.id);
                if (collision) {
                    collision.destroy();
                    streamManagers.delete(camera.id);
                }

                const { default: StreamManager } =
                    await import("./stream-manager.js");

                const manager = new StreamManager(
                    camera.id,
                    video,
                    targetUrl,
                    telemetry,
                    onStreamEnded,
                );

                manager.attachMedia();
                streamManagers.set(camera.id, manager);
            } finally {
                _unlock();
            }
        }, stagger);
    } catch (e) {
        _unlock();
        throw e;
    }
}

async function pollLocalJson() {
    if (document.hidden) return;
    if (_polling) return;
    _polling = true;
    try {
        const res = await fetch("/cameras.json", { priority: "low" });
        if (!res.ok) return;
        const data = await res.json();

        for (const newCam of data.cameras) {
            const oldCam = cameras.find((c) => c.id === newCam.id);
            if (!oldCam) continue;

            const prevStatus = currentCameraStates[newCam.id];
            const newStatus = newCam.status;

            oldCam.status = newStatus;
            oldCam.target_url = newCam.target_url;

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

                    if (cell.style.display !== "none") {
                        await initStream(cell, oldCam, oldCam.target_url);
                    }
                } else {
                    updateBadge(cell, "online");
                    cell.style.order = "-1";
                    if (!streamManagers.has(newCam.id) && cell.style.display !== "none") {
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
                    cell.querySelector("video").style.display = "none";
                    const overlay = cell.querySelector(".camera-offline-overlay");
                    if (overlay) overlay.style.display = "";
                }
                updateBadge(cell, "offline");
            }

            currentCameraStates[newCam.id] = newStatus;
        }

        applyFilters();
    } catch {
        /* silent */
    } finally {
        _polling = false;
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
                    const camera = cameras.find((c) => c.id === camId);
                    if (
                        camera &&
                        camera.status === "online" &&
                        !streamManagers.has(camId) &&
                        !initQueue.has(camId)
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
    const vh = window.innerHeight;
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

    const oldCell = document.querySelector(`.camera-cell[data-id="${fullscreenCameraId}"]`);
    if (oldCell) oldCell.classList.remove("fullscreen");

    const newCell = document.querySelector(`.camera-cell[data-id="${newCameraId}"]`);
    if (!newCell) {
        fullscreenCameraId = null;
        document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.remove("nav-visible"));
        showNavbar();
        resumeAllStreams();
        return;
    }

    fullscreenCameraId = newCameraId;
    newCell.classList.add("fullscreen");
    newCell.focus();

    document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.remove("hidden-nav"));
    clearTimeout(navbarTimeout);
    scheduleNavbarHide();

    const targetMgr = streamManagers.get(newCameraId);
    if (targetMgr && targetMgr._suspended) targetMgr.resume();

    streamManagers.forEach((mgr, id) => {
        if (id !== newCameraId) mgr.suspend();
    });

    const camera = cameras.find((c) => c.id === newCameraId);
    announce(`${camera?.name || ""} - fullscreen view`);
}

function enterFullscreen(cameraId) {
    const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
    if (!cell) return;
    fullscreenCameraId = cameraId;
    cell.classList.add("fullscreen");
    cell.focus();
    document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.add("nav-visible"));
    suspendOtherStreams(cameraId);
    clearTimeout(navbarTimeout);
    navbar.classList.add("hidden");
    grid?.classList.remove("navbar-visible");
    const camera = cameras.find((c) => c.id === cameraId);
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
    document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.remove("nav-visible"));
    showNavbar();
    resumeAllStreams();
    announce("Exited fullscreen view");
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    }
}

function handleFullscreenChange() {
    if (document.fullscreenElement) {
        const cell = document.fullscreenElement.closest(".camera-cell");
        if (!cell) return;
        const id = parseInt(cell.dataset.id, 10);
        fullscreenCameraId = id;
        cell.classList.add("fullscreen");
        cell.focus();
        document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.add("nav-visible"));
        clearTimeout(navbarTimeout);
        if (window.innerWidth > 768) {
            navbar.classList.add("hidden");
            grid?.classList.remove("navbar-visible");
        }
        suspendOtherStreams(id);
        const camera = cameras.find((c) => c.id === id);
        const displayName = camera?.name || "";
        announce(`${displayName} - fullscreen view`);
    } else {
        if (fullscreenCameraId !== null) return;
        document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.remove("nav-visible"));
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
        document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.add("hidden-nav"));
        grid?.classList.remove("navbar-visible");
    }, delay);
}

function showNavbar() {
    navbar.classList.remove("hidden");
    document.querySelectorAll(".fullscreen-nav-btn").forEach((btn) => btn.classList.remove("hidden-nav"));
    grid?.classList.add("navbar-visible");
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
    const activeCell = document.activeElement?.closest(".camera-cell");
    if (fullscreenCameraId !== null) {
        exitFullscreen();
    } else if (activeCell) {
        enterFullscreen(parseInt(activeCell.dataset.id, 10));
    } else if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen().catch(() => {});
    }
}

document.addEventListener("keydown", (e) => {
    const tag = e.target.tagName;
    const isInput = tag === "INPUT" || tag === "SELECT" || tag === "TEXTAREA";

    if (e.key === "Escape" && fullscreenCameraId !== null) {
        exitFullscreen();
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

document.addEventListener("click", (e) => {
    const navBtn = e.target.closest(".fullscreen-nav-btn");
    if (!navBtn || fullscreenCameraId === null) return;
    const visible = getVisibleCameras();
    if (visible.length <= 1) return;
    const idx = visible.findIndex((c) => c.id === fullscreenCameraId);
    if (idx === -1) return;
    if (navBtn.classList.contains("prev")) {
        switchFullscreen(visible[(idx - 1 + visible.length) % visible.length].id);
    } else if (navBtn.classList.contains("next")) {
        switchFullscreen(visible[(idx + 1) % visible.length].id);
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

function repositionNavbar() {
    const isMobile = window.innerWidth <= 768;
    const nav = document.getElementById("navbar");
    const g = document.getElementById("camera-grid");
    if (!nav || !g) return;
    const insideGrid = g.contains(nav);
    if (isMobile && !insideGrid) {
        g.insertBefore(nav, g.firstChild);
    } else if (!isMobile && insideGrid) {
        g.parentNode.insertBefore(nav, g);
    }
}

function initNavButtons() {
    const frag = document.createDocumentFragment();
    const prevBtn = document.createElement("button");
    prevBtn.className = "fullscreen-nav-btn prev";
    prevBtn.setAttribute("aria-label", "Previous camera");
    prevBtn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>`;
    const nextBtn = document.createElement("button");
    nextBtn.className = "fullscreen-nav-btn next";
    nextBtn.setAttribute("aria-label", "Next camera");
    nextBtn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>`;
    frag.appendChild(prevBtn);
    frag.appendChild(nextBtn);
    document.body.appendChild(frag);
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
    repositionNavbar();
    applyFilters();
}, 150));

window.addEventListener("beforeunload", () => {
    cleanupStreamManagers();
    telemetry.destroy();
    if (observer) observer.disconnect();
});

cameras.forEach((c) => {
    currentCameraStates[c.id] = c.status;
});

document.getElementById("refresh-btn")?.addEventListener("click", () => {
    pollLocalJson();
});

telemetry.init();
loadSelection();
initNavButtons();
initFilterSheet();
initSelectionPanel();
repositionNavbar();
buildGrid();
applyFilters();
showNavbar();
setInterval(pollLocalJson, 15000);
