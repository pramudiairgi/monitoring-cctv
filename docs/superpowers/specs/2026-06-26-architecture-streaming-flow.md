# Architecture Flow + Streaming Pipeline

> Monitoring CCTV — System Architecture & HLS Streaming Flow

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     EXTERNAL SOURCES (CCTV Cameras)                         │
│                                                                             │
│  ┌─────────────────────┐        ┌──────────────────────────────┐            │
│  │  Wowza Streaming     │        │  MediaMTX                    │            │
│  │  media.pcctabessmg   │        │  livepantau.semarangkota     │            │
│  │  .xyz:5443           │        │  .go.id                      │            │
│  │  (ABR: _adaptive.m3u8│        │  (single bitrate)            │            │
│  └──────────┬──────────┘        └──────────────┬───────────────┘            │
└─────────────┼──────────────────────────────────┼────────────────────────────┘
              │                                  │
              │         HTTP HLS Streams         │
              ▼                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      LARAVEL BACKEND (server)                               │
│                                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌────────────────────────┐      │
│  │  Scheduler       │  │  Commands        │  │  Controllers           │      │
│  │  (every min)     │  │                  │  │                        │      │
│  │  cameras:check   │──│ CameraCheckStatus│  │  MonitoringController  │      │
│  │  -status         │  │ (probe stream    │  │  (GET /)               │      │
│  │                  │  │  URL via HEAD)   │  │                        │      │
│  │  cameras:export  │  │ CameraExport     │  │  TelemetryController   │      │
│  │  (every 5 min)   │  │ (manual export)  │  │  (POST /api/telemetry) │      │
│  │                  │  │                  │  │                        │      │
│  │  telemetry:prune │  │ TelemetryPrune   │  │                        │      │
│  │  (daily)         │  │ (delete old)     │  │                        │      │
│  └────────┬─────────┘  └────────┬────────┘  └───────────┬────────────┘      │
│           │                     │                       │                   │
│           ▼                     ▼                       ▼                   │
│  ┌─────────────────────────────────────────────────────────────────┐       │
│  │                    DATABASE (PostgreSQL)                         │       │
│  │                                                                  │       │
│  │  ┌──────────┐  ┌──────────┐  ┌────────────────┐  ┌──────────┐  │       │
│  │  │ cameras  │  │categories│  │stream_telemetry │  │ users    │  │       │
│  │  └─────┬────┘  └─────┬────┘  └────────────────┘  └──────────┘  │       │
│  └────────┼──────────────┼─────────────────────────────────────────┘       │
│           │              │                                                 │
│           ▼              ▼                                                 │
│  ┌────────────────────────────────────────────────────────────────┐        │
│  │                     SERVICES                                   │        │
│  │                                                                 │        │
│  │  CameraExport Service                                          │        │
│  │  ┌─────────────────────────────────────────────────────┐       │        │
│  │  │ 1. Read cameras + categories from DB                │       │        │
│  │  │ 2. Rewrite stream URLs via HLS proxy config         │       │        │
│  │  │ 3. Write storage/app/public/cameras.json            │       │        │
│  │  └─────────────────────────────────────────────────────┘       │        │
│  └──────────────────────────┬─────────────────────────────────────┘        │
│                             │                                             │
│  ┌──────────────────────────▼─────────────────────────────────────┐        │
│  │                    OBSERVERS                                    │        │
│  │  CameraObserver ── on saved/deleted ──▶ CameraExport::handle() │        │
│  │  CategoryObserver ─ on saved/deleted ─▶ CameraExport::handle() │        │
│  └────────────────────────────────────────────────────────────────┘        │
└──────────────────────────┬──────────────────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                     PUBLIC / STORAGE                                      │
│                                                                           │
│  ┌──────────────────────────────────────────────────────────┐             │
│  │           cameras.json                                   │             │
│  │  (storage/app/public/cameras.json)                        │             │
│  │  ─ served via public/storage symlink                      │             │
│  │  ─ auto-generated by observers & scheduler                │             │
│  └─────────────────────┬────────────────────────────────────┘             │
└────────────────────────┼──────────────────────────────────────────────────┘
                         │
                         ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                      BROWSER / FRONTEND                                   │
│                                                                           │
│  GET / ──▶ monitoring.blade.php (server-rendered grid from cameras.json)  │
│                                                                           │
│  ┌──────────────────────────────────────────────────────────────────┐     │
│  │  monitoring.js (860 lines)                                       │     │
│  │                                                                   │     │
│  │  ┌──────────┐  ┌──────────┐  ┌────────────┐  ┌───────────────┐  │     │
│  │  │ buildGrid│  │ apply    │  │ Intersection│  │  stream-      │  │     │
│  │  │ ()       │  │ Filters  │  │ Observer    │──│  manager.js   │  │     │
│  │  │          │  │ ()       │  │ (lazy init) │  │  (346 lines)  │  │     │
│  │  └──────────┘  └──────────┘  └────────────┘  │               │  │     │
│  │                                               │  ┌─────────┐  │  │     │
│  │  ┌──────────┐  ┌──────────┐  ┌────────────┐  │  │ hls.js  │  │  │     │
│  │  │ Search + │  │ Full     │  │ Telemetry  │  │  │ (video  │  │  │     │
│  │  │ Filter   │  │ screen   │  │ Batcher    │  │  │ player) │  │  │     │
│  │  │          │  │          │  │ (60s/20ev) │  │  └─────────┘  │  │     │
│  │  └──────────┘  └──────────┘  └────────────┘  └───────────────┘  │     │
│  └──────────────────────────────────────────────────────────────────┘     │
│                                                                           │
│  DEPENDENCIES: hls.js ^1.6.16, TailwindCSS 4, Vite 8                     │
└────────────────────────────────────────────────────────────────────────────┘
```

## 2. Streaming Pipeline Flow

### Step-by-step pipeline:

```
 1. PAGE LOAD
     ├── monitoring.blade.php rendered (server-side from cameras.json)
     ├── monitoring.js executes
     ├── buildGrid() ── creates camera cells with loading placeholders
     ├── applyFilters() ── default: show only online cameras
     └── IntersectionObserver attached to each cell (rootMargin:300px)

 2. CELL SCROLLS INTO VIEWPORT (IntersectionObserver fires)
     ├── initStream(camera) called
     ├── Pre-flight HTTP HEAD check on stream_url
     │   ├── SUCCESS (200/206): proceed to init
     │   └── FAILED: mark as offline, show "Offline" badge
     ├── Dynamically import stream-manager.js (lazy loaded)
     └── new StreamManager(cameraElement, config)
         ├── Creates <video> element inside camera cell
         ├── Attaches hls.js instance with ABR config:
         │   ├── maxBufferLength: 30
         │   ├── maxMaxBufferLength: 60
         │   ├── enableWorker: true
         │   ├── lowLatencyMode: false
         │   ├── backbufferLength: 15
         │   └── liveSyncDurationCount: 5
         ├── hls.loadSource(stream_url)
         ├── hls.attachMedia(video)
         └── Video plays automatically (muted)

 3. ADAPTIVE URL PROBING (every 60s via checkAdaptiveUrl())
     ├── If camera has adaptive_url recorded
     │   └── Check if _adaptive.m3u8 is reachable
     │       ├── YES ──▶ Upgrade: destroy hls, re-create with
     │       │              adaptive_url (higher quality, ABR)
     │       └── NO ──▶ Keep using basic stream_url
     ├── Even if no adaptive_url: probe common patterns
     │   └── e.g. stream_720p.m3u8 → stream_adaptive.m3u8
     └── Max 1 adaptive check at a time (global lock)

 4. RECONNECT WITH EXPONENTIAL BACKOFF (on error/disconnect)
     ├── On hls.js MEDIA_ERROR or NETWORK_ERROR:
     │   ├── Log error to telemetry
     │   ├── Calculate delay = min(baseDelay * 2^retryCount, maxDelay)
     │   │   ├── baseDelay: 1000ms, maxDelay: 30000ms
     │   │   └── jitter: ±25% random
     │   ├── Show "Reconnecting..." overlay
     │   ├── After delay: destroy hls, re-create fresh instance
     │   └── After maxRetries (5): give up, show "Offline" badge
     ├── Cooldown period: after giving up, don't retry for 5 min
     └── probeOfflineCameras() runs every 15s globally to retry

 5. STALE STREAM DETECTION
     ├── Monitor video.currentTime every 5 seconds
     ├── If currentTime hasn't changed for 30 seconds:
     │   └── Treat as stale → trigger reconnect
     └── Only when video is playing (not paused)

 6. TELEMETRY COLLECTION & BATCHING
     ├── Events tracked: play, level_switch, buffering_start/end,
     │   error, reconnect, stale_detected, quality_change
     ├── Flush triggers:
     │   ├── Every 60 seconds (interval)
     │   ├── Queue reaches 20 events
     │   └── Page unload (sendBeacon)
     └── POST /api/telemetry ──▶ TelemetryController
         └── Dispatches ProcessTelemetryJob (Laravel queue)
             └── Bulk insert into stream_telemetry table

 7. PERIODIC DATA REFRESH
     ├── Every 60s: refreshCameraData()
     │   ├── Fetch /cameras.json
     │   ├── Compare with current data
     │   └── If changed: rebuild grid, re-map stream managers
     └── Keeps UI in sync with admin panel changes

 8. CLEANUP (page unload)
     ├── For each StreamManager: hls.destroy(), remove <video>
     ├── Disconnect IntersectionObserver
     └── Flush remaining telemetry via sendBeacon
```

## 3. Data Flow Detail

```
┌──────────┐   CRUD    ┌──────────┐   observe    ┌──────────┐
│ Filament  │──────────▶│ Database │─────────────▶│ Observers│
│ Admin     │  save/    │ (PgSQL)  │  saved/      │          │
│ Panel     │  delete   │          │  deleted     │ ───┐     │
└──────────┘           └──────────┘              └────│─────┘
                                                       │
                 ┌──────────────┐  triggers            │
                 │  Scheduler   │──────────────────────┘
                 │  (every min) │
                 │              │  ┌──────────────────┐
                 │  CameraCheck │  │ CameraExport     │
                 │  StatusCommand│◄┤ Service          │
                 │  (probe URL) │  │                  │
                 └──────────────┘  │ 1. Read DB       │
                                   │ 2. Rewrite URLs  │
                 ┌──────────────┐  │ 3. Write JSON    │
                 │  CameraExport│──┤                  │
                 │  (every 5m)  │  └────────┬─────────┘
                 └──────────────┘           │
                                            ▼
                                 ┌──────────────────┐
                                 │  cameras.json    │
                                 │  (static file)   │
                                 └────────┬─────────┘
                                          │
                                          ▼
                                 ┌──────────────────┐
                                 │  Browser Frontend │
                                 │  (monitoring.js)  │
                                 └────────┬─────────┘
                                          │
                        telemetry events  │
                          ┌───────────────┘
                          ▼
                 ┌──────────────────┐
                 │  Telemetry       │
                 │  Controller      │
                 │  POST /api/      │
                 │  telemetry       │
                 └────────┬─────────┘
                          │ dispatch
                          ▼
                 ┌──────────────────┐
                 │ ProcessTelemetry │
                 │ Job (queued)     │
                 └────────┬─────────┘
                          │ bulk insert
                          ▼
                 ┌──────────────────┐
                 │ stream_telemetry │
                 │ table            │
                 └──────────────────┘
```

## 4. Key Components

### Backend
- Laravel 13 + PHP 8.3
- Filament 3 admin panel
- PostgreSQL database
- 3 scheduled commands
- JSON bridge architecture
- Queue telemetry processing

### Frontend
- Vanilla JS (ES modules)
- hls.js ^1.6.16 video player
- IntersectionObserver lazy load
- Exponential backoff reconnect
- Adaptive URL probing
- Telemetry batching (60s/20ev)

### Streaming
- HLS protocol (HTTP Live Streaming)
- Wowza (ABR _adaptive.m3u8)
- MediaMTX (single bitrate)
- Client-side quality detection
- Stale stream detection (30s)
- Cooldown + jitter backoff

### Deployment
- Nginx reverse proxy + gzip
- PHP-FPM 8.3
- Supervisor queue + scheduler
- Vite 8 build pipeline
- TailwindCSS 4 styling
- Playwright E2E tests
