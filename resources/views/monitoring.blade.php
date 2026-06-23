@extends('layouts.monitoring')

@section('content')
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

  <script id="monitoring-data" type="application/json">@json($cameras)</script>
@endsection
