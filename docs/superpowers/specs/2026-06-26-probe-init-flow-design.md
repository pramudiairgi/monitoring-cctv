# Probe & Init Stream Flow — Design

## Problem

Saat ini probe + init stream memiliki **duplikasi HTTP call** yang tidak perlu:

1. `probeSingleCamera()` — GET `stream_url`
2. `initStream()` → `checkStreamAvailability()` — GET `stream_url` lagi
3. `initStream()` → `checkStreamAvailability()` — GET `adaptive_url`
4. `scheduleAdaptiveRecheck()` — interval 60s terpisah, GET `adaptive_url`

Selain boros bandwidth, logic retry/cooldown yang kompleks (`_probeRetries`, `_probeState`, `_adaptiveRetries`, `_adaptiveCooldown`) membuat alur sulit diikuti.

## Solusi

Unifikasi probe + init menjadi satu alur linear dengan max 2 HTTP call per camera.

### Alur Baru

```
probeSingleCamera(camera):
  GET stream_url
  ├── !200 → markOffline(), return
  └── 200 → cek adaptive_url?
              ├── tidak ada → initStream(stream_url)
              └── ada → GET adaptive_url
                          ├── 200 → initStream(adaptive_url)
                          └── !200 → initStream(stream_url)
```

### Perubahan

**Hapus:**
- `checkStreamAvailability()` — tidak diperlukan lagi
- `_probeRetries`, `_probeState`, `_adaptiveRetries`, `_adaptiveCooldown`, `_nextProbeTime` — tidak ada retry
- `scheduleAdaptiveRecheck()` — adaptive ditentukan inline saat probe

**Sederhanakan:**
- `probeSingleCamera()` — handle seluruh decision tree, panggil `initStream()` langsung dengan URL final
- `initStream(cell, camera, targetUrl)` — terima URL langsung, tanpa pre-flight check
- `refreshCameraData()` — sinkron data saja, tanpa re-init logic yang kompleks

**State yang tersisa per camera:**
- `_reachable` — true/false
- `_isCooldownUntil` — cooldown antar probe
- `_adaptive_ready` — flag adaptive sudah siap
