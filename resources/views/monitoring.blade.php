@extends('layouts.monitoring')

@section('content')
  {{-- Patrol Live Toast --}}
  <div id="patrol-toast" class="patrol-toast" role="status" aria-live="polite" hidden>
    <div class="patrol-toast-inner">
      <div class="patrol-toast-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
      </div>
      <div class="patrol-toast-content">
        <div class="patrol-toast-title">Patrol Live</div>
        <div class="patrol-toast-desc">
          {{ $patrolAlert['online'] }}/{{ $patrolAlert['total'] }} kamera patroli sedang live
        </div>
      </div>
      <button id="patrol-toast-dismiss" class="patrol-toast-dismiss" aria-label="Dismiss notification">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <nav id="navbar" class="navbar" aria-label="Camera filters">
    
    <div class="navbar-inner glass-panel">
      <div class="search-row">
        <svg class="filter-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.34-4.34"/>
        </svg>
        <input id="search" type="text" class="filter-input" placeholder="Search camera..." aria-label="Search cameras">
        <button id="refresh-btn" class="navbar-icon-btn" aria-label="Refresh camera data">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 2v6h-6"/>
            <path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
            <path d="M3 22v-6h6"/>
            <path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
          </svg>
        </button>
        <button id="select-btn" class="navbar-icon-btn" aria-label="Select visible cameras">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
          </svg>
        </button>
        <button id="filter-toggle" class="navbar-icon-btn filter-toggle" aria-label="Toggle filters">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/>
          </svg>
        </button>
        <button id="info-btn" class="navbar-icon-btn" aria-label="Keyboard shortcuts">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
          </svg>
        </button>
      </div>
      <div class="filter-divider"></div>
      <select id="status-filter" class="filter-select" aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="online" selected>Online</option>
        <option value="offline">Offline</option>
      </select>
      <div class="filter-divider"></div>
      <div class="cat-drop" data-cat-dropdown>
        <button type="button" class="cat-drop-btn" data-cat-button aria-haspopup="true" aria-expanded="false">
          <span data-cat-label>Semua</span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="cat-drop-panel" role="group" aria-label="Pilih kategori">
          <button type="button" class="cat-reset" data-cat-reset>Tampilkan semua</button>
          @foreach($categories as $cat)
            <label class="cat-check"><input type="checkbox" value="{{ $cat['value'] }}" data-slug="{{ $cat['value'] }}"><span>{{ $cat['label'] }}</span></label>
          @endforeach
        </div>
      </div>
      <span id="live-counter" class="live-counter" aria-live="polite"></span>

    </div>
  </nav>

  <div id="dock-wrapper">
  <nav id="dock" aria-label="Navigasi bawah">
    <button id="dock-camera-btn" type="button" class="dock-btn" aria-label="Daftar kamera">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2" />
        <path d="M10 9l5 3-5 3V9z" />
      </svg>
      <span>Kamera</span>
    </button>
    <button id="dock-info-btn" type="button" class="dock-btn" aria-label="Keyboard shortcuts">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
      </svg>
      <span>Info</span>
    </button>
    <div class="dock-hill" aria-hidden="true">
      <svg viewBox="0 0 112 28" preserveAspectRatio="none" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,28 C13,28 20,28 26,19 C33,9 41,0 56,0 C71,0 79,9 86,19 C92,28 99,28 112,28" />
      </svg>
    </div>
    <button id="dock-menu-btn" type="button" class="dock-fab" aria-label="Buka menu" aria-expanded="false" aria-controls="filter-sheet">
      <svg class="icon-menu" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
      <svg class="icon-close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
  </nav>
  </div>

  <div id="camera-grid" class="camera-grid" role="region" aria-label="Camera grid">
    @foreach($cameras as $c)
      <div class="camera-cell"
           data-id="{{ $c['id'] }}"
           data-name="{{ strtolower($c['name']) }}"
           data-category="{{ $c['category'] }}"
           data-status="{{ $c['status'] }}"
           tabindex="0"
           role="button"
           aria-label="{{ $c['name'] }} - {{ $c['status'] }}">
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
          <span class="placeholder-caption">{{ $c['name'] }}</span>
          <button class="cell-play-btn" style="display:none" aria-label="Putar tayangan"></button>
        </div>
        <video muted autoplay playsinline></video>
        <button class="cell-pause-btn" style="display:none" aria-label="Berhenti memutar"></button>
        <div class="camera-placeholder-info">
          <span class="status-badge {{ $c['status'] }}"><span class="badge-name">{{ $c['name'] }}</span><span class="badge-status"> - {{ $c['status'] }}</span></span>
          <button class="cell-speak-toggle" style="display:none" aria-label="Nyalakan suara"></button>
        </div>
        <button class="fullscreen-close" aria-label="Exit fullscreen">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
          </svg>
        </button>
      </div>
    @endforeach
  </div>

  <div id="filter-sheet-overlay" class="filter-sheet-overlay" aria-hidden="true"></div>
  <aside id="filter-sheet" class="filter-sheet" role="dialog" aria-label="Camera filters">
    <div class="filter-sheet-handle"></div>
    <div class="filter-sheet-header">
      <h2>Filters</h2>
      <span id="camera-count" class="camera-count" aria-live="polite"></span>
      <button id="filter-sheet-close" class="filter-sheet-close" aria-label="Close filters">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <div class="filter-sheet-body">
      <input id="search-popup" type="text" class="filter-input sheet-search mobile-only" placeholder="Search camera..." aria-label="Search cameras" autocomplete="off">
      <div class="mobile-only">
        <h4 class="pop-h">Status Kamera</h4>
        <div class="chip-rowm" data-chip-group="status" role="group" aria-label="Filter status">
          <button type="button" class="chip-m chip-off" data-value="">Semua</button>
          <button type="button" class="chip-m chip-on" data-value="online"><span class="dot dot-green"></span>Online</button>
          <button type="button" class="chip-m chip-off" data-value="offline"><span class="dot dot-red"></span>Offline</button>
        </div>
      </div>
      <div class="mobile-only">
        <h4 class="pop-h">Kategori</h4>
        <div class="chip-rowm" data-chip-group="category" role="group" aria-label="Filter kategori">
          <button type="button" class="chip-m chip-on" data-value="">Semua</button>
          @foreach($categories as $cat)
            <button type="button" class="chip-m chip-off" data-value="{{ $cat['value'] }}">{{ $cat['label'] }}</button>
          @endforeach
        </div>
      </div>
      <div class="cat-drop" data-cat-dropdown>
        <button type="button" class="cat-drop-btn" data-cat-button aria-haspopup="true" aria-expanded="false">
          <span data-cat-label>Semua</span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="cat-drop-panel" role="group" aria-label="Pilih kategori">
          <button type="button" class="cat-reset" data-cat-reset>Tampilkan semua</button>
          @foreach($categories as $cat)
            <label class="cat-check"><input type="checkbox" value="{{ $cat['value'] }}" data-slug="{{ $cat['value'] }}"><span>{{ $cat['label'] }}</span></label>
          @endforeach
        </div>
      </div>
      <select id="status-filter-sheet" class="filter-sheet-select" aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="online" selected>Online</option>
        <option value="offline">Offline</option>
      </select>
       <div class="filter-sheet-buttons">
        <button id="refresh-btn-sheet" class="filter-sheet-btn" aria-label="Refresh camera data">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
          </svg>
          Refresh
        </button>
        <button id="select-btn-sheet" class="filter-sheet-btn" aria-label="Select visible cameras">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
          </svg>
          Select Cameras
        </button>
      </div>
    </div>
  </aside>

  <svg width="0" height="0" style="position: absolute" aria-hidden="true">
    <defs>
      <clipPath id="valleyClip">
        <path id="valleyPath" d="" />
      </clipPath>
      <clipPath id="shortcutsClip">
        <path id="shortcutsPath" d="" />
      </clipPath>
      <clipPath id="cameraClip">
        <path id="cameraPath" d="" />
      </clipPath>
    </defs>
  </svg>

  <div id="shortcuts-wrap" class="pop-wrap">
  <section id="shortcuts-popup" class="pop-card" aria-label="Keyboard shortcuts">
    <div class="pop-head">
      <h3 class="pop-title">Shortcuts</h3>
      <button id="shortcuts-close" type="button" class="pop-x" aria-label="Tutup shortcuts">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <ul class="sc-list">
      <li class="sc-row">
        <span>Fullscreen grid</span>
        <kbd class="kbd">F</kbd>
      </li>
      <li class="sc-row">
        <span>Pindah kamera</span>
        <span class="sc-keys">
          <kbd class="kbd">←</kbd>
          <kbd class="kbd">→</kbd>
        </span>
      </li>
      <li class="sc-row">
        <span>Tutup panel</span>
        <kbd class="kbd">Esc</kbd>
      </li>
    </ul>
  </section>
  </div>

  <div id="camera-wrap" class="pop-wrap">
  <section id="camera-popup" class="pop-card" aria-label="Daftar kamera">
    <div class="pop-head">
      <h3 class="pop-title">Daftar Kamera</h3>
      <button id="camera-popup-close" type="button" class="pop-x" aria-label="Tutup daftar kamera">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <ul id="panel-camera-list" class="panel-camera-list" aria-label="Select cameras"></ul>
  </section>
  </div>

  <div id="announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>

  <script id="monitoring-data" type="application/json">@json($cameras)</script>
  <script id="playback-config" type="application/json">@json($playbackSettings ?? [])</script>
  <script id="patrol-alert-data" type="application/json">@json($patrolAlert ?? [])</script>
@endsection
