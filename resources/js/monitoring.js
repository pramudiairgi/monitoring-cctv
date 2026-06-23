import StreamManager from './stream-manager.js';

const DATA_SCRIPT = document.getElementById('monitoring-data');
const CAMERAS = DATA_SCRIPT ? JSON.parse(DATA_SCRIPT.textContent) : [];

const NAVBAR_HIDE_DELAY = 3000;
const TELEMETRY_ENDPOINT = '/api/telemetry';
const TELEMETRY_FLUSH_INTERVAL = 30000;
const TELEMETRY_FLUSH_THRESHOLD = 10;

let cameras = CAMERAS;
let searchQuery = '';
let selectedCategory = '';
let selectedStatus = '';
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

  if (selectedStatus) {
    filtered = filtered.filter(c => c.status === selectedStatus);
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
    });
  await Promise.allSettled(probes);
}

function cameraPriority(c) {
  if (c._reachable === true) return 0;
  if (c._reachable === null && c.status === 'online') return 1;
  return 2;
}

function buildGrid() {
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
      <div class="camera-placeholder">Loading stream...</div>
      <video muted autoplay playsinline></video>
      <div class="camera-offline-overlay" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M8 12h8"/>
        </svg>
        <span>Offline</span>
      </div>
      <div class="camera-placeholder-info">
        <span class="camera-placeholder-name">${escapeHtml(c.name)}</span>
        <span class="status-badge ${escapeHtml(c.status)}">${escapeHtml(c.status)}</span>
      </div>
    `;

    fragment.appendChild(cell);
  });

  grid.replaceChildren(fragment);
  cleanupStreamManagers();
  initObserver();
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

function initStream(cell, camera) {
  const video = cell.querySelector('video');
  const placeholder = cell.querySelector('.camera-placeholder');

  const isOnline = camera._reachable ?? (camera.status === 'online');
  if (!isOnline) {
    placeholder.textContent = 'Offline';
    video.style.display = 'none';
    return;
  }

  if (streamManagers.has(camera.id)) return;

  const manager = new StreamManager(
    camera.id,
    video,
    camera.stream_url,
    camera.adaptive_url,
    telemetry,
  );

  manager.attachMedia();
  streamManagers.set(camera.id, manager);
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
          initStream(cell, camera);
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
    cell.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const targetAspect = 16 / 9;
  const isMobile = vw <= 768;
  const maxCols = isMobile ? 2 : 4;

  let cols, rows;

  if (visibleCount === 0) {
    cols = 1;
    rows = 1;
  } else if (visibleCount === 2) {
    cols = 1;
    rows = 2;
  } else if (visibleCount <= 1) {
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
        cells[i].style.justifySelf = 'center';
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
  navbar.classList.add('hidden');
  grid?.classList.remove('navbar-visible');
}

function exitFullscreen() {
  if (fullscreenCameraId === null) return;
  const cell = document.querySelector(`.camera-cell[data-id="${fullscreenCameraId}"]`);
  if (cell) cell.classList.remove('fullscreen');
  fullscreenCameraId = null;
  showNavbar();
}

function showNavbar() {
  navbar.classList.remove('hidden');
  grid?.classList.add('navbar-visible');
  clearTimeout(navbarTimeout);
  navbarTimeout = setTimeout(() => {
    navbar.classList.add('hidden');
    grid?.classList.remove('navbar-visible');
  }, NAVBAR_HIDE_DELAY);
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
searchInput?.addEventListener('focus', showNavbar);
categoryFilter?.addEventListener('focus', showNavbar);
statusFilter?.addEventListener('focus', showNavbar);

window.addEventListener('resize', debounce(applyFilters, 150));

window.addEventListener('beforeunload', () => {
  cleanupStreamManagers();
  telemetry.destroy();
  if (observer) observer.disconnect();
});

telemetry.init();
(async () => {
  await probeCameras();
  buildGrid();
  applyFilters();
  showNavbar();
})();
