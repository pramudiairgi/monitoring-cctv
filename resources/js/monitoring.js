const DATA_SCRIPT = document.getElementById("monitoring-data");
const CAMERAS = DATA_SCRIPT ? JSON.parse(DATA_SCRIPT.textContent) : [];

const NAVBAR_HIDE_DELAY = 2000;
const NAVBAR_HIDE_DELAY_GRID = 2000;
const TELEMETRY_ENDPOINT = "/api/telemetry";
const TELEMETRY_FLUSH_INTERVAL = 300000;
const TELEMETRY_FLUSH_THRESHOLD = 20;

let cameras = CAMERAS;
let currentCameraStates = {};
let searchQuery = "";
let selectedCategory = "";
let selectedStatus = "online";
let fullscreenCameraId = null;
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

    grid.replaceChildren(fragment);
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

    if (cameraCount)
        cameraCount.textContent = `${visibleCount} / ${cameras.length} cameras`;
}

function suspendOtherStreams(activeId) {
    streamManagers.forEach((manager, id) => {
        if (id !== activeId) manager.suspend();
    });
}

function resumeAllStreams() {
    streamManagers.forEach((manager) => manager.resume());
}

function enterFullscreen(cameraId) {
    const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
    if (!cell) return;
    fullscreenCameraId = cameraId;
    cell.classList.add("fullscreen");
    cell.focus();
    navbar.classList.add("hidden");
    grid?.classList.remove("navbar-visible");
    const camera = cameras.find((c) => c.id === cameraId);
    const displayName = camera?.name || "";
    announce(`${displayName} - fullscreen view`);
    if (cell.requestFullscreen) {
        cell.requestFullscreen().catch(() => {
            cell.classList.remove("fullscreen");
            fullscreenCameraId = null;
            showNavbar();
        });
    }
}

function exitFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    } else if (fullscreenCameraId !== null) {
        const cell = document.querySelector(
            `.camera-cell[data-id="${fullscreenCameraId}"]`,
        );
        if (cell) cell.classList.remove("fullscreen");
        fullscreenCameraId = null;
        showNavbar();
        resumeAllStreams();
        announce("Exited fullscreen view");
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
        navbar.classList.add("hidden");
        grid?.classList.remove("navbar-visible");
        suspendOtherStreams(id);
        const camera = cameras.find((c) => c.id === id);
        const displayName = camera?.name || "";
        announce(`${displayName} - fullscreen view`);
    } else {
        const prevCell = document.querySelector(
            `.camera-cell[data-id="${fullscreenCameraId}"]`,
        );
        if (prevCell) prevCell.classList.remove("fullscreen");
        fullscreenCameraId = null;
        showNavbar();
        resumeAllStreams();
        announce("Exited fullscreen view");
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
        grid?.classList.remove("navbar-visible");
    }, delay);
}

function showNavbar() {
    navbar.classList.remove("hidden");
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

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && fullscreenCameraId !== null) {
        exitFullscreen();
    }
});

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

window.addEventListener("resize", debounce(applyFilters, 150));

window.addEventListener("beforeunload", () => {
    cleanupStreamManagers();
    telemetry.destroy();
    if (observer) observer.disconnect();
});

cameras.forEach((c) => {
    currentCameraStates[c.id] = c.status;
});

telemetry.init();
buildGrid();
applyFilters();
showNavbar();
setInterval(pollLocalJson, 15000);
