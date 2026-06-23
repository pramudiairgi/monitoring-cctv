# Monitoring View Refactor Design

**Date:** 2026-06-23
**Project:** monitoring-cctv

## Problem

`resources/views/monitoring.blade.php` is 852 lines with inline CSS (~430 lines) and inline JS (~350 lines) mixed with Blade/HTML. No separation of concerns, no Vite/asset pipeline usage (despite Vite being installed), potential memory leaks, and inconsistent patterns.

## Goals

1. Separate HTML, CSS, and JS into dedicated files following standard Laravel patterns
2. Use Vite for asset bundling (already configured in project)
3. Install HLS.js via npm instead of CDN
4. Fix identified defects (HLS leak, variable shadowing, accessibility)
5. Zero visual/behavioral change

## Approach

### Files to Create

| File | Purpose |
|------|---------|
| `resources/views/layouts/monitoring.blade.php` | Layout: DOCTYPE, head, body shell, `@vite()`, `@yield` |
| `resources/css/monitoring.css` | All CSS from `<style>` block |
| `resources/js/monitoring.js` | All JS from `<script>` block, with HLS.js import |

### Files to Modify

| File | Change |
|------|--------|
| `resources/views/monitoring.blade.php` | Rewrite: extends layout, remove all inline CSS/JS, keep only HTML structure + Blade directives + data bridge |
| `vite.config.js` | Add `resources/css/monitoring.css` and `resources/js/monitoring.js` as entry points |
| `package.json` | Add `hls.js` as dependency |

### Data Bridge Pattern

Instead of `@json()` inside inline JS, use a JSON script tag:

```blade
<script id="monitoring-data" type="application/json">@json($cameras)</script>
```

JS reads it via:
```js
const cameras = JSON.parse(document.getElementById('monitoring-data').textContent);
```

### Defect Fixes Included

1. **HLS cleanup**: Destroy HLS instances when cells are replaced or instances are stale
2. **Variable shadowing**: Remove shadowed `grid` declaration in `applyFilters()`
3. **mousemove throttle**: Added throttle to `showNavbar` calls from `mousemove`
4. **Accessibility**: `aria-label` on cells includes status; offline overlay has screen-reader text
5. **No more CDN**: HLS.js bundled via npm/Vite

### What Stays the Same

- All CSS variables, class names, and design tokens
- All JavaScript behavior, grid algorithm, fullscreen toggle, navbar auto-hide
- Blade directives (`@foreach`, `@json`)
- Controller and route logic
- No conversion to Tailwind (kept as custom CSS)

## Flow After Refactor

```
User hits /
  → MonitoringController reads cameras.json
  → passes $cameras, $categories to monitoring.blade.php
  → monitoring.blade.php extends layouts/monitoring.blade.php
  → Layout loads monitoring.css + monitoring.js via @vite()
  → Blade renders HTML + JSON data bridge in script#monitoring-data
  → monitoring.js reads data, builds grid, initializes HLS
```

## Files Not Changed

- `app/Http/Controllers/MonitoringController.php`
- `routes/web.php`
- `resources/css/app.css` (Tailwind)
- `resources/js/app.js` (empty, kept for welcome page)
