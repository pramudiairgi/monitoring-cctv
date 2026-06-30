# Mobile Navbar Bottom Sheet — Plan

## Konsep

**Desktop (≥769px):** navbar inline seperti commit terakhir — search, category, status, button inline.

**Mobile (≤768px):** navbar hanya search bar + tombol ≡ (filter toggle). Kategori, status, refresh, select pindah ke bottom sheet yang muncul saat ≡ di-tap.

## Layout Mobile

### Navbar (selalu visible, top of screen)
```
┌──────────────────────────────────┐
│ 🔍 [Search cameras..........] ≡ │
└──────────────────────────────────┘
```

### Bottom Sheet (slide up dari bawah)
```
┌──────────────────────────────────┐
│ ═══ ⋮⋮⋮ ═══  (drag handle)      │
├──────────────────────────────────┤
│ ✕ Filters                       │
├──────────────────────────────────┤
│ [All Categories ▾]              │
├──────────────────────────────────┤
│ [Online ▾]                      │
├──────────────────────────────────┤
│ 🔄 Refresh    ✏️ Select cameras  │
└──────────────────────────────────┘
```

## Files & Perubahan

### 1. `resources/views/monitoring.blade.php`
- Tambah `#filter-toggle` button (icon ≡) di search-row, sebelum `</div>` search-row
- Tambah bottom sheet HTML (`#filter-sheet`) setelah `<nav>`, sebelum `</div>` content

### 2. `resources/css/monitoring.css` — di `@media (max-width: 768px)`
- `.filter-divider` → `display: none` (sudah ada)
- `#category-filter, #status-filter` → `display: none` (sembunyi dari navbar)
- `.navbar-icon-btn` → `display: none` (refresh & select pindah ke sheet)
- `.filter-toggle` → `display: flex` (munculin tombol ≡)
- `.filter-sheet`:
  - `position: fixed; bottom: 0; left: 0; right: 0`
  - `z-index: calc(var(--z-overlay) + 2)`
  - `background: var(--color-surface)`
  - `border-radius: var(--radius-lg) var(--radius-lg) 0 0`
  - `transform: translateY(100%); transition: transform 0.35s cubic-bezier`
  - `max-height: 50vh`
- `.filter-sheet.open` → `transform: translateY(0)`
- `.filter-sheet-overlay`: fullscreen gelap, `z-index: calc(var(--z-overlay) + 1)`
- Drag handle `.filter-sheet-handle`

### 3. `resources/js/monitoring.js`
- `initFilterSheet()` function:
  - Query elements: sheet, overlay, toggle button, close button
  - `open()`: add `open` class + `aria-hidden` removal
  - `close()`: remove `open` class
  - Events: toggle click, overlay click, close click, Escape key
- Panggil `initFilterSheet()` di init (setelah `initNavButtons()`)
- `refresh-btn` di sheet → panggil `pollLocalJson()` (event listener sudah ada di `document.getElementById("refresh-btn")`)
- `select-btn` di sheet → panggil open selection panel (event listener sudah di `initSelectionPanel()`)

## Eksekusi Order

1. Blade: tambah button ≡ + bottom sheet HTML
2. CSS: mobile hide elements, styling bottom sheet
3. JS: initFilterSheet + event wiring
4. Build & verify
