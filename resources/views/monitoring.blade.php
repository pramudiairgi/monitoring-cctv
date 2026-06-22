<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>CCTV Monitoring</title>
  <style>
    :root {
      --color-primary: #007aff;
      --color-accent: #5ac8fa;
      --color-success: #34c759;
      --color-error: #ff3b30;
      --color-bg-primary: #000000;
      --color-surface: #1c1c1e;
      --color-text-primary: #ffffff;
      --color-text-secondary: #8e8e93;

      --glass-bg: rgba(0, 0, 0, 0.65);
      --glass-bg-light: rgba(0, 0, 0, 0.5);
      --glass-border: rgba(255, 255, 255, 0.1);
      --glass-blur: blur(20px) saturate(180%);
      --glass-shadow: none;
      --glass-shadow-hover: none;
      --glass-highlight: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, transparent 40%);

      --space-1: 4px;
      --space-2: 8px;
      --space-3: 12px;
      --space-4: 16px;
      --space-5: 20px;
      --space-6: 24px;

      --text-xs: 12px;
      --text-sm: 14px;
      --text-base: 16px;

      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --radius-pill: 9999px;

      --z-panel: 200;
      --z-overlay: 400;

      --duration-fast: 0.15s;
      --easing-default: cubic-bezier(0.4, 0, 0.2, 1);

      --font-sans: "Fira Sans", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      width: 100%;
      height: 100%;
      height: 100dvh;
      overflow: hidden;
      padding-top: env(safe-area-inset-top, 0px);
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    body {
      font-family: var(--font-sans);
      background: var(--color-bg-primary);
      color: var(--color-text-primary);
      -webkit-font-smoothing: antialiased;
    }

    .skip-link {
      position: absolute;
      top: -40px;
      left: 0;
      background: var(--color-primary);
      color: white;
      padding: var(--space-2) var(--space-4);
      z-index: 9999;
      transition: top 0.3s;
      text-decoration: none;
    }

    .skip-link:focus {
      top: 0;
    }

    /* Glass Panel */
    .glass-panel {
      background: var(--glass-bg);
      -webkit-backdrop-filter: var(--glass-blur);
      backdrop-filter: var(--glass-blur);
      border: 0.5px solid var(--glass-border);
      border-radius: var(--radius-lg);
      position: relative;
      transition: border-color var(--duration-fast) var(--easing-default);
    }

    .glass-panel::before {
      content: "";
      position: absolute;
      inset: 0;
      background: var(--glass-highlight);
      border-radius: inherit;
      pointer-events: none;
    }

    .glass-panel:hover {
      border-color: rgba(255, 255, 255, 0.18);
    }

    /* Camera Grid */
    .camera-grid {
      width: 100vw;
      height: 100vh;
      display: grid;
      gap: 0;
      background: #000;
      overflow: hidden;
    }

    /* Camera Cell */
    .camera-cell {
      position: relative;
      overflow: hidden;
      background: #000;
      margin: 0;
      padding: 0;
      border: none;
      outline: none;
      line-height: 0;
      font-size: 0;
      cursor: pointer;
    }

    .camera-cell video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .camera-cell.fullscreen {
      position: fixed;
      inset: 0;
      z-index: var(--z-overlay);
      cursor: pointer;
    }

    .camera-cell.fullscreen video {
      object-fit: contain;
    }

    /* Camera Placeholder */
    .camera-placeholder {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: var(--color-bg-primary);
    }

    .camera-placeholder-info {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: var(--space-2) var(--space-3);
      background: linear-gradient(transparent, rgba(0, 0, 0, 0.85));
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .camera-placeholder-name {
      font-size: var(--text-sm);
      color: var(--color-text-primary);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* Status Badge */
    .status-badge {
      padding: var(--space-1) var(--space-2);
      border-radius: var(--radius-sm);
      font-size: var(--text-xs);
      text-transform: capitalize;
      font-weight: 500;
    }

    .status-badge.online {
      color: var(--color-success);
      background: rgba(52, 199, 89, 0.15);
    }

    .status-badge.offline {
      color: var(--color-error);
      background: rgba(255, 59, 48, 0.15);
    }

    /* Navbar */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: var(--z-panel);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: var(--space-4) 0 0;
      transform: translateY(0);
      opacity: 1;
      transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1);
      pointer-events: auto;
    }

    .navbar.hidden {
      transform: translateY(-100%);
      opacity: 0;
      pointer-events: none;
    }

    .navbar-inner {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      padding: var(--space-2) var(--space-5);
      border-radius: var(--radius-pill);
      animation: glass-in 0.3s var(--easing-default) forwards;
    }

    .search-row {
      display: contents;
    }

    .filter-icon {
      color: var(--color-text-secondary);
      flex-shrink: 0;
    }

    .filter-divider {
      width: 1px;
      height: 18px;
      background: var(--glass-border);
      flex-shrink: 0;
    }

    .filter-input {
      width: 200px;
      border: none;
      background: transparent;
      color: var(--color-text-primary);
      font-size: var(--text-sm);
      font-family: var(--font-sans);
      outline: none;
      padding: 0;
      height: 24px;
      position: relative;
      z-index: 1;
    }

    .filter-input::placeholder {
      color: var(--color-text-secondary);
    }

    .filter-select {
      border: none;
      background: transparent;
      color: var(--color-text-primary);
      font-size: var(--text-sm);
      font-family: var(--font-sans);
      outline: none;
      cursor: pointer;
      padding: 0 var(--space-5) 0 var(--space-2);
      height: 24px;
      min-width: 120px;
      -webkit-appearance: none;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238E8E93' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0 center;
      position: relative;
      z-index: 1;
    }

    .filter-select option {
      background: var(--color-surface);
      color: var(--color-text-primary);
    }

    .navbar-link {
      background: rgba(0, 122, 255, 0.15);
      border: 0.5px solid rgba(0, 122, 255, 0.30);
      border-radius: var(--radius-pill);
      padding: var(--space-1) var(--space-3);
      font-size: var(--text-sm);
      color: var(--color-text-primary);
      text-decoration: none;
      transition: background 0.2s, border-color 0.2s;
    }

    .navbar-link:hover {
      background: rgba(0, 122, 255, 0.25);
      border-color: rgba(0, 122, 255, 0.50);
    }

    .camera-count {
      font-size: var(--text-xs);
      color: var(--color-text-secondary);
      margin-top: var(--space-2);
    }

    /* Animations */
    @keyframes glass-in {
      from { opacity: 0; transform: scale(0.95) translateY(-8px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .navbar {
        padding: var(--space-3) var(--space-3) 0;
      }

      .navbar-inner {
        flex-wrap: wrap;
        padding: var(--space-3);
        gap: var(--space-2);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 100%;
      }

      .filter-divider {
        display: none;
      }

      .search-row {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        width: 100%;
      }

      .filter-input {
        flex: 1;
        min-width: 0;
        min-height: 44px;
        padding: var(--space-2);
        background-color: var(--glass-bg-light);
        border-radius: var(--radius-md);
      }

      .filter-select {
        flex: 1;
        min-width: 0;
        min-height: 44px;
        padding: var(--space-2);
        background-color: var(--glass-bg-light);
        border-radius: var(--radius-md);
      }

      .navbar-link {
        width: 100%;
        text-align: center;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .camera-count {
        text-align: center;
        width: 100%;
      }

      .camera-grid {
        gap: 2px;
      }

      .camera-cell {
        min-height: 120px;
      }

      .camera-placeholder-info {
        padding: var(--space-3);
        min-height: 44px;
      }

      .camera-placeholder-name {
        font-size: var(--text-base);
      }
    }

    /* Focus visible styles */
    :focus-visible {
      outline: 2px solid var(--color-primary);
      outline-offset: 2px;
    }

    :focus:not(:focus-visible) {
      outline: none;
    }
  </style>
</head>
<body>
  <a href="#camera-grid" class="skip-link">Skip to camera grid</a>

  <div id="camera-grid" class="camera-grid" role="region" aria-label="Camera grid"></div>

  <nav id="navbar" class="navbar" aria-label="Camera filters">
    <div class="navbar-inner glass-panel">
      <div class="search-row">
        <svg class="filter-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.34-4.34"/>
        </svg>
        <input id="search" type="text" class="filter-input" placeholder="Search camera..." aria-label="Search cameras">
      </div>
      <div class="filter-divider"></div>
      <select id="category-filter" class="filter-select" aria-label="Filter by category">
        <option value="">All Categories</option>
        @foreach($categories as $cat)
          <option value="{{ $cat['value'] }}">{{ $cat['label'] }}</option>
        @endforeach
      </select>
      <div class="filter-divider"></div>
      <select id="status-filter" class="filter-select" aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="online">Online</option>
        <option value="offline">Offline</option>
      </select>
    </div>
    <span id="camera-count" class="camera-count" aria-live="polite"></span>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
  <script>
    (() => {
      'use strict';

      const CAMERAS = @json($cameras);
      const NAVBAR_HIDE_DELAY = 3000;

      let cameras = CAMERAS;
      let searchQuery = '';
      let selectedCategory = '';
      let selectedStatus = '';
      let fullscreenCameraId = null;
      let navbarTimeout = null;
      let resizeTimeout = null;
      const hlsInstances = new Map();
      let observer = null;

      const grid = document.getElementById('camera-grid');
      const searchInput = document.getElementById('search');
      const categoryFilter = document.getElementById('category-filter');
      const statusFilter = document.getElementById('status-filter');
      const cameraCount = document.getElementById('camera-count');
      const navbar = document.getElementById('navbar');

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

      function buildGrid() {
        const fragment = document.createDocumentFragment();

        cameras.forEach(c => {
          const cell = document.createElement('div');
          cell.className = 'camera-cell';
          cell.dataset.id = c.id;
          cell.dataset.name = c.name.toLowerCase();
          cell.dataset.category = c.category;
          cell.dataset.status = c.status;
          cell.setAttribute('tabindex', '0');
          cell.setAttribute('role', 'button');
          cell.setAttribute('aria-label', c.name);

          cell.innerHTML = `
            <div class="camera-placeholder">Loading stream...</div>
            <video muted autoplay playsinline></video>
            <div class="camera-placeholder-info">
              <span class="camera-placeholder-name">${escapeHtml(c.name)}</span>
              <span class="status-badge ${escapeHtml(c.status)}">${escapeHtml(c.status)}</span>
            </div>
          `;

          fragment.appendChild(cell);
        });

        grid.replaceChildren(fragment);
        initObserver();
      }

      function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
      }

      function initHlsPlayer(cell, camera) {
        const video = cell.querySelector('video');
        const placeholder = cell.querySelector('.camera-placeholder');
        const url = camera.stream_url;

        if (camera.status === 'offline') {
          placeholder.textContent = 'Offline';
          video.style.display = 'none';
          return;
        }

        if (Hls.isSupported()) {
          const hls = new Hls({
            enableWorker: true,
            lowLatencyMode: true
          });
          hls.loadSource(url);
          hls.attachMedia(video);
          hls.on(Hls.Events.MANIFEST_PARSED, () => {
            placeholder.style.display = 'none';
            video.play().catch(() => {});
          });
          hls.on(Hls.Events.ERROR, (_, data) => {
            if (data.fatal) {
              placeholder.textContent = 'Stream unavailable';
              video.style.display = 'none';
            }
          });
          hlsInstances.set(camera.id, hls);
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
          video.src = url;
          video.addEventListener('loadedmetadata', () => {
            placeholder.style.display = 'none';
            video.play().catch(() => {});
          });
          video.addEventListener('error', () => {
            placeholder.textContent = 'Stream unavailable';
            video.style.display = 'none';
          });
        } else {
          placeholder.textContent = 'HLS not supported';
          video.style.display = 'none';
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
              if (camera && !hlsInstances.has(camId)) {
                initHlsPlayer(cell, camera);
              }
            }
          });
        }, { rootMargin: '300px' });

        document.querySelectorAll('.camera-cell').forEach(cell => {
          observer.observe(cell);
        });
      }

      function applyFilters() {
        const grid = document.getElementById('camera-grid');
        const count = document.getElementById('camera-count');
        if (!grid) return;

        const visibleIds = getFilteredIds();
        let visibleCount = 0;

        grid.querySelectorAll('.camera-cell').forEach(cell => {
          const id = parseInt(cell.dataset.id);
          const show = visibleIds.has(id);
          cell.style.display = show ? '' : 'none';
          if (show) visibleCount++;
        });

        // Calculate optimal grid layout — exact 16:9 cells
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const targetAspect = 16 / 9;
        const isMobile = vw <= 768;
        const maxCols = isMobile ? 2 : 4;

        let cols, rows;
        if (visibleCount <= 1) {
          cols = 1;
          rows = 1;
        } else {
          // Find layout where cells are closest to 16:9
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
        }

        // Use 1fr rows to fill entire viewport height
        const cellW = vw / cols;

        grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
        grid.style.gridTemplateRows = `repeat(${rows}, 1fr)`;

        if (count) count.textContent = `${visibleCount} / ${cameras.length} cameras`;
      }

      function enterFullscreen(cameraId) {
        const cell = document.querySelector(`.camera-cell[data-id="${cameraId}"]`);
        if (!cell) return;
        fullscreenCameraId = cameraId;
        cell.classList.add('fullscreen');
        navbar.classList.add('hidden');
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
        clearTimeout(navbarTimeout);
        navbarTimeout = setTimeout(() => navbar.classList.add('hidden'), NAVBAR_HIDE_DELAY);
      }

      function debounce(fn, delay) {
        let timer;
        return (...args) => {
          clearTimeout(timer);
          timer = setTimeout(() => fn(...args), delay);
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
      document.addEventListener('mousemove', showNavbar);
      document.addEventListener('touchstart', showNavbar);
      searchInput?.addEventListener('focus', showNavbar);
      categoryFilter?.addEventListener('focus', showNavbar);
      statusFilter?.addEventListener('focus', showNavbar);

      window.addEventListener('resize', debounce(applyFilters, 150));

      window.addEventListener('beforeunload', () => {
        hlsInstances.forEach(hls => hls.destroy());
        hlsInstances.clear();
        if (observer) observer.disconnect();
      });

      buildGrid();
      applyFilters();
      showNavbar();
    })();
  </script>
</body>
</html>
