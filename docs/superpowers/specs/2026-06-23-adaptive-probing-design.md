# Adaptive Probing & Status Checker

## 1. Objectives

1. **Adaptive URL probing** — Camera website A memiliki `_adaptive.m3u8` yang hanya tersedia saat stream live. Sistem harus otomatis mulai dengan `stream_url` (single) lalu upgrade ke `adaptive_url` saat terdeteksi live.
2. **Buffer stability** — hls.js config dioptimasi untuk single bitrate: buffer besar, prioritas stabilitas > latency.
3. **Auto online/offline** — Backend periodic check status camera via HTTP probe, update database, export ke JSON. Frontend sync periodik tanpa reload halaman.
4. **Default filter: online only** — Monitoring page default hanya tampilkan camera online.

## 2. Architecture

```
┌─────────────────────────────────────────────┐
│                Backend (Laravel)            │
│                                              │
│  cron * * * * *                              │
│    ↓                                         │
│  cameras:check-status                        │
│    ↓ probe stream_url setiap camera          │
│    ↓ update DB jika status berubah           │
│    ↓ auto-trigger CameraExport → cameras.json│
└──────────────────────┬──────────────────────┘
                       │ HTTP fetch /cameras.json setiap 60 detik
┌──────────────────────▼──────────────────────┐
│              Frontend (Browser)             │
│                                              │
│  Page load → fetch cameras.json              │
│  ↓ buildGrid() + initStream()               │
│  ↓ filter default: status=online             │
│  ↓                                           │
│  setInterval 30s:                            │
│    └─ probe adaptive_url camera              │
│       └─ 200 OK → upgradeToAdaptive()        │
│  setInterval 60s:                            │
│    └─ refreshCameraData()                    │
│       └─ sync status dari servers.json       │
└─────────────────────────────────────────────┘
```

## 3. Backend Components

### 3.1 `CameraCheckStatusCommand`

```
// app/Console/Commands/CameraCheckStatusCommand.php
// php artisan cameras:check-status

class CameraCheckStatusCommand extends Command
{
    protected $signature = 'cameras:check-status';
    protected $description = 'Probe stream URLs and update camera status';

    public function handle(): int
    {
        $cameras = Camera::all();
        
        foreach ($cameras as $camera) {
            $online = $this->probeUrl($camera->stream_url);
            
            if ($camera->status !== ($online ? 'online' : 'offline')) {
                $camera->update(['status' => $online ? 'online' : 'offline']);
            }
        }
        
        return Command::SUCCESS;
    }
    
    private function probeUrl(string $url): bool
    {
        // HTTP HEAD request dengan timeout 5 detik
        // Return true jika response 200
    }
}
```

### 3.2 Cron Integration

**`deploy/supervisor.conf` — tambah entry:**
```
[program:laravel-schedule]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitoring-cctv/artisan schedule:work
autostart=true
autorestart=true
user=www-data
```

**Atau crontab standar:**
```
* * * * * cd /var/www/monitoring-cctv && php artisan schedule:run >> /dev/null 2>&1
```

**`app/Console/Kernel.php`:**
```
protected function schedule(Schedule $schedule): void
{
    $schedule->command('cameras:check-status')->everyMinute();
}
```

## 4. Frontend Components

### 4.1 `stream-manager.js` — Config Tuning

| Key | Old | New | Alasan |
|-----|-----|-----|--------|
| `lowLatencyMode` | `true` | `false` | Prioritas buffer > latency |
| `liveSyncDuration` | `5` | `15` | Buffer 15 detik |
| `liveMaxLatencyDuration` | `10` | `30` | Toleransi latensi lebih besar |
| `maxBufferLength` | `30` | `60` | Buffer sampai 60 detik |
| `maxMaxBufferLength` | `60` | `120` | Buffer maks 120 detik |
| `backbufferLength` | `5` | `30` | Jangan discard segment terlalu cepat |

### 4.2 `stream-manager.js` — Method `upgradeToAdaptive()`

- Destroy HLS instance saat ini
- Buat HLS baru dengan adaptive URL
- Attach ke video element yang sama
- Reset reconnect counter
- Track event 'level_switch' ke telemetry

### 4.3 `monitoring.js` — Adaptive Probing

**Di `probeCameras()`:**
```
if (camera.adaptive_url) {
  try {
    const res = await fetch(camera.adaptive_url, 
      { method: 'GET', signal: AbortSignal.timeout(5000) });
    camera._adaptive_ready = res.ok;
  } catch {
    camera._adaptive_ready = false;
  }
} else {
  camera._adaptive_ready = null;
}
```

**Fungsi baru `scheduleAdaptiveRecheck()`:**
- `setInterval` 30 detik
- Loop camera dengan `adaptive_url` ada tapi `_adaptive_ready` masih false
- Jika response 200 → set `_adaptive_ready = true`, panggil `upgradeToAdaptive()`

### 4.4 `monitoring.js` — Status Sync

**Fungsi baru `refreshCameraData()`:**
- `setInterval` 60 detik
- `fetch('/storage/cameras.json')` (atau via route)
- Bandingkan status baru vs lama
- Jika offline → destroy stream, show overlay
- Jika online → init stream, hide overlay
- Update status badge + aria-label

### 4.5 `monitoring.js` — Default Filter

```
let selectedStatus = 'online';  // default: show only online
```

**`monitoring.blade.php`:**
```
<option value="online" selected>Online</option>
```

## 5. Data Flow

### Adaptive Upgrade Flow

```
Page Load → load stream_url (single) → play
    ↓ 30 detik
probe adaptive_url → 200 OK?
    ├─ Ya → destroy HLS → load adaptive_url (multi-bitrate) → attachMedia → ABR aktif
    └─ Tidak → tetap streaming via stream_url → coba lagi 30 detik
```

### Status Sync Flow

```
[cron 1 menit] probe camera URLs
    ↓ status berubah?
    ├─ Ya → update DB → trigger export → cameras.json diperbarui
    └─ Tidak → skip

[browser 60 detik] fetch cameras.json
    ↓ status berubah?
    ├─ Ya → update UI: destroy/init stream, toggle overlay, update badge
    └─ Tidak → skip
```

## 6. Edge Cases

| Kasus | Penanganan |
|-------|-----------|
| `adaptive_url` null | Skip probing, tetap single |
| `adaptive_url` 404 terus | Recheck tiap 30 detik sampai menjadi 200 |
| Camera offline di DB tapi stream nyala | Backend probe akan detect dan update ke online |
| Camera online di DB tapi stream mati | Backend probe akan detect dan update ke offline |
| Browser offline total | fetch cameras.json gagal → silent catch, UI tidak berubah |
| Upgrade saat buffering | `upgradeToAdaptive()` destroy HLS lama, reload baru — buffering selesai setelah attach |
| Banyak camera adaptive ready bersamaan | Upgrade async per camera, tidak blocking satu sama lain |

## 7. Files Changed

| File | Change Type |
|------|-------------|
| `app/Console/Commands/CameraCheckStatusCommand.php` | CREATE |
| `app/Console/Kernel.php` | EDIT (add schedule) |
| `resources/js/stream-manager.js` | EDIT (config + upgradeToAdaptive) |
| `resources/js/monitoring.js` | EDIT (probing + refresh + default filter) |
| `resources/views/monitoring.blade.php` | EDIT (default selected=online) |
| `deploy/supervisor.conf` | EDIT (tambah laravel-schedule) |

## 8. Out of Scope

- Transcoding proxy (website B) — ditunda untuk diskusi tim
- GPU/NVENC optimization
- Real-time WebSocket status update (cukup polling periodik)
- UI redesign atau animasi baru
