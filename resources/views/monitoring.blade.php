@extends('layouts.monitoring')

@section('content')
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
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M10 9l5 3-5 3V9z"/>
          </svg>
          <span class="placeholder-text">Loading stream...</span>
        </div>
        <video muted autoplay playsinline></video>
        <div class="camera-placeholder-info">
          <span class="status-badge {{ $c['status'] }}">{{ $c['name'] }} - {{ $c['status'] }}</span>
        </div>
        <button class="fullscreen-close" aria-label="Exit fullscreen">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
          </svg>
        </button>
      </div>
    @endforeach
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
        <option value="online" selected>Online</option>
        <option value="offline">Offline</option>
      </select>
    </div>
    <span id="camera-count" class="camera-count" aria-live="polite"></span>
  </nav>

  <div id="announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>

  <script id="monitoring-data" type="application/json">@json($cameras)</script>
@endsection
