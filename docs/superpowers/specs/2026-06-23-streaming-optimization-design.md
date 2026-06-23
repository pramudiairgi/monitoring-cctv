# Streaming Optimization Design

**Date:** 2026-06-23
**Project:** monitoring-cctv

## Context

Monitoring dashboard menampilkan CCTV stream HLS dari 2 sumber eksternal:
- **Wowza Streaming Engine** (media.pcctabessmg.xyz:5443) — patroli cameras, mendukung ABR via `_adaptive.m3u8`
- **MediaMTX** (livepantau.semarangkota.go.id) — public facility cameras, single-bitrate

Aplikasi adalah konsumen HLS murni, bukan encoder. Optimasi dilakukan di client-side.

## Goals

1. ABR (Adaptive Bitrate) tuning untuk Wowza streams via `_adaptive.m3u8`
2. Buffer management dan live sync optimization untuk semua stream
3. Auto-reconnect dengan exponential backoff untuk ketahanan stream
4. Asynchronous telemetry untuk monitoring performa streaming

## 1. Database: `adaptive_url` Field

**Migration:** `add_adaptive_url_to_cameras_table`
- Kolom `adaptive_url` nullable string setelah `stream_url`
- Diisi manual via Filament admin dengan URL `_adaptive.m3u8` untuk stream yang mendukung ABR

**Model:** `Camera.php` — tambah `adaptive_url` ke `$fillable`

**Export:** `CameraExport.php` — tambah `adaptive_url` ke output JSON

**Filament Resource:** `CameraResource.php` — tambah field `adaptive_url` di form

Data flow: DB → CameraExport → cameras.json → Blade → JS StreamManager

## 2. StreamManager (JS)

**File:** `resources/js/stream-manager.js`

Class yang membungkus lifecycle HLS.js:

| Method | Fungsi |
|--------|--------|
| `constructor(id, videoEl, streamUrl, adaptiveUrl, telemetry)` | Init dengan data camera |
| `getConfig()` | HLS.js config dengan ABR + buffer tuning |
| `attachMedia()` | Init HLS.js, pilih adaptiveUrl jika ada |
| `reconnect()` | Exponential backoff, max 3 attempts |
| `destroy()` | Cleanup HLS instance |
| `getCurrentBitrate()` | Untuk telemetry |
| `getBufferHealth()` | Untuk telemetry |

**HLS.js Configuration:**
```js
{
  enableWorker: true,
  lowLatencyMode: true,
  liveSyncDuration: 3,
  liveMaxLatencyDuration: 6,
  maxBufferLength: 15,
  maxMaxBufferLength: 30,
  startLevel: -1,
  abrEwmaDefaultEstimate: 500000,
  abrBandwidthUpFactor: 0.7,
  abrBandwidthDownFactor: 0.4,
  capLevelToPlayerSize: true,
}
```

**Reconnect Logic:**
```
Attempt 1: 2s delay
Attempt 2: 4s delay
Attempt 3: 8s delay
Max 3 attempts, stop & show "Stream unavailable" if all fail
```

## 3. Monitoring JS Update

`resources/js/monitoring.js` — `initHlsPlayer()` refactor:

```js
// Before: langsung new Hls(...)
// After:
function initStream(cell, camera) {
  const manager = new StreamManager(
    camera.id,
    cell.querySelector('video'),
    camera.stream_url,
    camera.adaptive_url,      // baru
    telemetry                  // baru
  );
  manager.attachMedia();
  streamManagers.set(camera.id, manager);
}
```

## 4. Telemetry

### Database Migration: `create_stream_telemetry_table`

```sql
stream_telemetry:
  id, camera_id, camera_name
  bitrate_kbps, resolution, buffer_health, latency_ms
  event_type (play/error/buffering/level_switch)
  error_message (nullable)
  user_agent, created_at
```

### Backend

- **Controller:** `TelemetryController` — POST `/api/telemetry`, validasi + insert
- **Route:** `/api/telemetry` via `routes/api.php`
- **Model:** `StreamTelemetry`

### Frontend: TelemetryCollector

```
TelemetryCollector
├── init(telemetryEndpoint)       → set endpoint URL
├── track(eventType, data)        → kumpulkan event
├── flush()                       → kirim batch via Beacon API
└── auto: flush setiap 30s / 10 events
```

Events tracked:
- `play`: stream start, bitrate, resolution
- `buffering`: start + end, duration
- `error`: error type, message
- `level_switch`: bitrate up/down
- `reconnect`: attempt number

## Files Changed

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_add_adaptive_url_to_cameras_table.php` |
| Create | `database/migrations/xxxx_create_stream_telemetry_table.php` |
| Edit | `app/Models/Camera.php` |
| Edit | `app/Services/CameraExport.php` |
| Edit | `app/Filament/Resources/CameraResource.php` |
| Create | `app/Http/Controllers/TelemetryController.php` |
| Create | `app/Models/StreamTelemetry.php` |
| Edit | `routes/api.php` |
| Create | `resources/js/stream-manager.js` |
| Edit | `resources/js/monitoring.js` |

## Future ABR Readiness

- Tidak perlu kode berubah saat server encoding menambahkan varian bitrate baru
- `_adaptive.m3u8` akan otomatis menyajikan semua varian yang tersedia
- hls.js ABR controller akan memilih varian optimal berdasarkan bandwidth real-time
- `capLevelToPlayerSize: true` memastikan tidak boros bandwidth untuk resolusi di atas ukuran cell
