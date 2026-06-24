const DATA_SCRIPT = document.getElementById('monitoring-data');
const CAMERAS = DATA_SCRIPT ? JSON.parse(DATA_SCRIPT.textContent) : [];

const NAVBAR_HIDE_DELAY = 3000;
const NAVBAR_HIDE_DELAY_GRID = 5000;
const TELEMETRY_ENDPOINT = '/api/telemetry';
const TELEMETRY_FLUSH_INTERVAL = 30000;
const TELEMETRY_FLUSH_THRESHOLD = 10;

let cameras = CAMERAS;
let searchQuery = '';
let selectedCategory = '';
let selectedStatus = 'online';
let fullscreenCameraId = null;
let navbarTimeout = null;
let streamManagers = new Map();
let observer = null;

const grid = document.getElementById('camera-grid');
const searchInput = document.getElementById('search');
const categoryFilter = document.getElementById('category-filter');
const statusFilter = document.getElementById('status-filter');
const cameraCount = document.getElementById('camera-count');
const navbar = document.getElementById('navbar');

const telemetry = {
  queue: [],
  timer: null,

  init() {
    this.timer = setInterval(() => this.flush(), TELEMETRY_FLUSH_INTERVAL);
    window.addEventListener('beforeunload', () => {
      this.flush(true);
    });
  },

  track(data) {
    const payload = {
      ...data,
      camera_name: cameras.find(c => c.id === data.camera_id)?.name || null,
      user_agent: navigator.userAgent,
      timestamp: Date.now(),
    };
    this.queue.push(payload);
    if (this.queue.length >= TELEMETRY_FLUSH_THRESHOLD) {
      this.flush();
    }
  },

  flush(useBeacon = false) {
    if (this.queue.length === 0) return;
    const batch = this.queue.splice(0, this.queue.length);
    const body = JSON.stringify(batch);

    if (useBeacon && navigator.sendBeacon) {
      navigator.sendBeacon(TELEMETRY_ENDPOINT, body);
    } else {
      fetch(TELEMETRY_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        keepalive: true,
      }).catch(() => {});
    }
  },

  destroy() {
    clearInterval(this.timer);
    this.flush(true);
  },
};

function getFilteredIds() {
  let filtered = cameras;

  if (selectedStatus === 'online') {
    filtered = filtered.filter(c => c._reachable ?? c.status === 'online');
  } else if (selectedStatus === 'offline') {
    filtered = filtered.filter(c => !(c._reachable ?? c.status === 'online'));
  }

  if (selectedCategory) {
    filtered = filtered.filter(c => c.category === selectedCategory);
  }

  if (searchQuery) {
    const q = searchQuery.toLowerCase();
    filtered = filtered.filter(c =>
      c.name.toLowerCase().includes(q) ||
      c.category.toLowerCase().includes(q)
    );
  }

  return new Set(filtered.map(c => c.id));
}

function updateBadge(cell, status) {
  cell.dataset.status = status;
  const name = cell.querySelector('.camera-placeholder-name');
  const badge = cell.querySelector('.status-badge');
  if (badge) {
    badge.className = `status-badge ${status}`;
    badge.textContent = `${name?.textContent || ''} - ${status}`;
  }
  cell.setAttribute('aria-label', `${name?.textContent || ''} - ${status}`);
}

async function probeCameras() {
  const probes = cameras
    .filter(c => c.status === 'online')
    .map(async (camera) => {
      try {
        const res = await fetch(camera.stream_url, {
          method: 'GET',
          signal: AbortSignal.timeout(5000),
        });
        camera._reachable = res.ok;
      } catch {
        camera._reachable = false;
      }
      camera._adaptive_ready = false;
    });
  await Promise.allSettled(probes);
}

function cameraPriority(c) {
  if (c._reachable === true) return 0;
  if (c._reachable === null && c.status === 'online') return 1;
  return 2;
}

function buildGrid() {
  if (grid.querySelectorAll('.camera-cell').length > 0) {
    cleanupStreamManagers();
    initObserver();
    return;
  }

  const fragment = document.createDocumentFragment();

  const sorted = [...cameras].sort((a, b) => cameraPriority(a) - cameraPriority(b));

  sorted.forEach(c => {
    const cell = document.createElement('div');
    cell.className = 'camera-cell';
    cell.dataset.id = c.id;
    cell.dataset.name = c.name.toLowerCase();
    cell.dataset.category = c.category;
    cell.dataset.status = c.status;
    cell.setAttribute('tabindex', '0');
    cell.setAttribute('role', 'button');
    cell.setAttribute('aria-label', `${c.name} - ${c.status}`);

    cell.innerHTML = `
      <div class="camera-placeholder">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="M10 9l5 3-5 3V9z"/>
        </svg>
        <span class="placeholder-text">Loading stream...</span>
      </div>
      <video muted autoplay playsinline></video>
      <div class="camera-offline-overlay" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M8 12h8"/>
        </svg>
        <span>${escapeHtml(c.name)} - Offline</span>
      </div>
      <div class="camera-placeholder-info">
        <span class="camera-placeholder-name">${escapeHtml(c.name)}</span>
        <span class="status-badge ${escapeHtml(c.status)}">${escapeHtml(c.name)} - ${escapeHtml(c.status)}</span>
      </div>
      <button class="fullscreen-close" aria-label="Exit fullscreen">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
        </svg>
      </button>
    `;

    if (c._reachable) {
      cell.style.order = '-1';
    }

    fragment.appendChild(cell);
  });

  grid.replaceChildren(fragment);
  cleanupStreamManagers();
  initObserver();
}

function onStreamEnded(cameraId) {
  const camera = cameras.find(c => c.id === cameraId);
  if (!camera) return;

  camera._reachable = false;
  streamManagers.delete(cameraId);

  const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
  if (!cell) return;

  cell.style.order = '';
  const ph = cell.querySelector('.camera-placeholder');
  const pt = ph?.querySelector('.placeholder-text');
  if (pt) pt.textContent = `${camera.name} - Offline`;
  cell.querySelector('video').style.display = 'none';
  const overlay = cell.querySelector('.camera-offline-overlay');
  if (overlay) overlay.style.display = '';
  updateBadge(cell, 'offline');
  applyFilters();
}

function cleanupStreamManagers() {
  streamManagers.forEach(manager => manager.destroy());
  streamManagers.clear();
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function initStream(cell, camera) {
  const video = cell.querySelector('video');
  const placeholder = cell.querySelector('.camera-placeholder');

  const isOnline = camera._reachable ?? (camera.status === 'online');
  if (!isOnline) {
    const textEl = placeholder.querySelector('.placeholder-text');
    if (textEl) textEl.textContent = 'Offline';
    video.style.display = 'none';
    return;
  }

  if (streamManagers.has(camera.id)) return;

  const { default: StreamManager } = await import('./stream-manager.js');

  const manager = new StreamManager(
    camera.id,
    video,
    camera.stream_url,
    camera.adaptive_url,
    telemetry,
    onStreamEnded,
  );

  if (camera._adaptive_ready) {
    manager.setAdaptiveReady(true);
  }

  manager.attachMedia();
  streamManagers.set(camera.id, manager);
}

function scheduleAdaptiveRecheck() {
  setInterval(async () => {
    for (const camera of cameras) {
      if (!camera.adaptive_url || camera._adaptive_ready) continue;
      if (!camera._reachable) continue;

      try {
        const res = await fetch(camera.adaptive_url, {
          method: 'GET',
          signal: AbortSignal.timeout(5000),
        });

        if (res.ok) {
          camera._adaptive_ready = true;
          const mgr = streamManagers.get(camera.id);
          if (mgr) {
            mgr.upgradeToAdaptive(camera.adaptive_url);
          }
        }
      } catch {
        /* masih belum live, coba lagi nanti */
      }
    }
  }, 30000);
}

async function probeSingleCamera(camera) {
  try {
    const res = await fetch(camera.stream_url, {
      method: 'GET',
      signal: AbortSignal.timeout(5000),
    });

    if (res.ok && !camera._reachable) {
      camera._reachable = true;

      const cell = document.querySelector(`.camera-cell[data-id="${camera.id}"]`);
      if (!cell) return;

      cell.style.order = '-1';
      cell.querySelector('video').style.display = '';
      const overlay = cell.querySelector('.camera-offline-overlay');
      if (overlay) overlay.style.display = 'none';

      updateBadge(cell, 'online');

      await initStream(cell, camera);
      applyFilters();
    }
  } catch {
    /* masih offline */
  }
}

function probeOfflineCameras() {
  for (const camera of cameras) {
    if (camera._reachable) continue;
    probeSingleCamera(camera);
  }
}

async function refreshCameraData() {
  try {
    const res = await fetch('/cameras.json');
    if (!res.ok) return;
    const data = await res.json();
    const newCameras = data.cameras;

    for (const newCam of newCameras) {
      const oldCam = cameras.find(c => c.id === newCam.id);
      if (!oldCam) continue;

      oldCam.status = newCam.status;

      if (newCam.status === 'online' && oldCam._reachable === undefined) {
        oldCam._reachable = true;
      }

      const cell = document.querySelector(`.camera-cell[data-id="${newCam.id}"]`);
      if (!cell) continue;

      const isReachable = oldCam._reachable ?? newCam.status === 'online';

      if (isReachable) {
        cell.style.order = '-1';
        const mgr = streamManagers.get(newCam.id);
        if (!mgr) {
          cell.querySelector('video').style.display = '';
          const overlay = cell.querySelector('.camera-offline-overlay');
          if (overlay) overlay.style.display = 'none';
          await initStream(cell, newCam);
        }
        updateBadge(cell, 'online');
      } else {
        cell.style.order = '';
        const mgr = streamManagers.get(newCam.id);
        if (mgr) { mgr.destroy(); streamManagers.delete(newCam.id); }
        oldCam._adaptive_ready = null;
        const ph = cell.querySelector('.camera-placeholder');
        const pt = ph?.querySelector('.placeholder-text');
        if (pt) pt.textContent = `${newCam.name} - Offline`;
        cell.querySelector('video').style.display = 'none';
        const overlay = cell.querySelector('.camera-offline-overlay');
        if (overlay) overlay.style.display = '';
        updateBadge(cell, 'offline');
      }
    }

    applyFilters();
  } catch {
    /* silent: fetch gagal, coba lagi nanti */
  }
}

function initObserver() {
  if (observer) observer.disconnect();

  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const cell = entry.target;
        const camId = parseInt(cell.dataset.id, 10);
        const camera = cameras.find(c => c.id === camId);
        if (camera && !streamManagers.has(camId)) {
          initStream(cell, camera).catch(() => {});
        }
      }
    });
  }, { rootMargin: '300px' });

  document.querySelectorAll('.camera-cell').forEach(cell => {
    observer.observe(cell);
  });
}

function applyFilters() {
  const visibleIds = getFilteredIds();
  let visibleCount = 0;

  grid.querySelectorAll('.camera-cell').forEach(cell => {
    const id = parseInt(cell.dataset.id);
    const show = visibleIds.has(id);
    if (!show) {
      const mgr = streamManagers.get(id);
      if (mgr) {
        mgr.destroy();
        streamManagers.delete(id);
      }
    }
    cell.style.display = show ? '' : 'none';
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
    const cells = grid.querySelectorAll('.camera-cell:not([style*="display: none"])');
    cells.forEach(cell => cell.style.justifySelf = '');
    if (remainder !== 0) {
      const lastRowStart = cells.length - remainder;
      for (let i = lastRowStart; i < cells.length; i++) {
        cells[i].style.justifySelf = 'start';
      }
    }
  }

  grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
  grid.style.gridTemplateRows = `repeat(${rows}, 1fr)`;

  let emptyMsg = grid.querySelector('.empty-message');
  if (visibleCount === 0) {
    if (!emptyMsg) {
      emptyMsg = document.createElement('div');
      emptyMsg.className = 'empty-message';
      emptyMsg.textContent = 'No cameras match your filter';
      grid.appendChild(emptyMsg);
    }
    emptyMsg.style.display = 'flex';
  } else if (emptyMsg) {
    emptyMsg.style.display = 'none';
  }

  if (cameraCount) cameraCount.textContent = `${visibleCount} / ${cameras.length} cameras`;
}

function enterFullscreen(cameraId) {
  const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
  if (!cell) return;
  fullscreenCameraId = cameraId;
  cell.classList.add('fullscreen');
  cell.focus();
  navbar.classList.add('hidden');
  grid?.classList.remove('navbar-visible');
  const name = cell.querySelector('.camera-placeholder-name')?.textContent || '';
  announce(`${name} - fullscreen view`);
}

function exitFullscreen() {
  if (fullscreenCameraId === null) return;
  const cell = document.querySelector(`.camera-cell[data-id="${fullscreenCameraId}"]`);
  if (cell) {
    cell.classList.remove('fullscreen');
    cell.focus();
  }
  fullscreenCameraId = null;
  showNavbar();
  announce('Exited fullscreen view');
}

function scheduleNavbarHide() {
  clearTimeout(navbarTimeout);
  const delay = fullscreenCameraId !== null ? NAVBAR_HIDE_DELAY : NAVBAR_HIDE_DELAY_GRID;
  navbarTimeout = setTimeout(() => {
    navbar.classList.add('hidden');
    grid?.classList.remove('navbar-visible');
  }, delay);
}

function showNavbar() {
  navbar.classList.remove('hidden');
  grid?.classList.add('navbar-visible');
  clearTimeout(navbarTimeout);
  scheduleNavbarHide();
}

function announce(message) {
  const el = document.getElementById('announcements');
  if (el) {
    el.textContent = '';
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
      setTimeout(() => { inThrottle = false; }, limit);
    }
  };
}

searchInput?.addEventListener('input', debounce(e => {
  searchQuery = e.target.value;
  applyFilters();
}, 150));

categoryFilter?.addEventListener('change', e => {
  selectedCategory = e.target.value;
  applyFilters();
});

statusFilter?.addEventListener('change', e => {
  selectedStatus = e.target.value;
  applyFilters();
});

grid?.addEventListener('click', e => {
  const closeBtn = e.target.closest('.fullscreen-close');
  if (closeBtn) {
    exitFullscreen();
    return;
  }
  const cell = e.target.closest('.camera-cell');
  if (!cell) return;
  if (fullscreenCameraId === null) {
    enterFullscreen(parseInt(cell.dataset.id, 10));
  } else {
    exitFullscreen();
  }
});

grid?.addEventListener('keydown', e => {
  if (e.key === 'Enter' || e.key === ' ') {
    const closeBtn = e.target.closest('.fullscreen-close');
    if (closeBtn) return;
    e.preventDefault();
    const cell = e.target.closest('.camera-cell');
    if (!cell) return;
    if (fullscreenCameraId === null) {
      enterFullscreen(parseInt(cell.dataset.id, 10));
    } else {
      exitFullscreen();
    }
  }
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && fullscreenCameraId !== null) {
    exitFullscreen();
  }
});

navbar?.addEventListener('mouseenter', showNavbar);
document.addEventListener('mousemove', throttle(showNavbar, 100));
document.addEventListener('touchstart', showNavbar);
searchInput?.addEventListener('focus', () => {
  clearTimeout(navbarTimeout);
});
searchInput?.addEventListener('blur', scheduleNavbarHide);

categoryFilter?.addEventListener('focus', () => {
  clearTimeout(navbarTimeout);
});
categoryFilter?.addEventListener('blur', scheduleNavbarHide);

statusFilter?.addEventListener('focus', () => {
  clearTimeout(navbarTimeout);
});
statusFilter?.addEventListener('blur', scheduleNavbarHide);

window.addEventListener('resize', debounce(applyFilters, 150));

window.addEventListener('beforeunload', () => {
  cleanupStreamManagers();
  telemetry.destroy();
  if (observer) observer.disconnect();
});

function initOnlineStreams() {
  for (const camera of cameras) {
    if (camera.status !== 'online') continue;
    const cell = document.querySelector(`.camera-cell[data-id="${camera.id}"]`);
    if (!cell) continue;
    initStream(cell, camera).catch(() => {});
  }
}

telemetry.init();
buildGrid();
applyFilters();
showNavbar();
initOnlineStreams();
probeCameras().then(() => {
  applyFilters();
  scheduleAdaptiveRecheck();
});
setInterval(probeOfflineCameras, 15000);
setInterval(refreshCameraData, 60000);
