# Backend-Driven Probing Architecture

## Problem

Frontend saat ini melakukan HTTP GET langsung ke URL stream eksternal (.m3u8) untuk mengecek status camera. Jika stream mati (404), terjadi rentetan error di Console dan pemborosan request.

## Solution

Backend (Laravel Scheduler) bertugas melakukan probing ke URL eksternal, menentukan status, dan menulis hasilnya ke `/storage/app/public/cameras.json`. Frontend HANYA membaca JSON lokal — tidak ada request ke URL eksternal.

## Backend Changes

### CameraCheckStatusCommand
- Probe `stream_url` (HEAD)
- Jika ada `adaptive_url`, probe juga (HEAD)
- Tentukan `target_url` final: prefer `adaptive_url` jika online, fallback ke `stream_url`
- Simpan `status` dan `target_url` ke DB (atau computed di export)

### CameraExport
- Tambah field `target_url` di output JSON per camera
- JSON sekarang: `{id, name, stream_url, adaptive_url, target_url, category, status}`

### Schedule (existing)
- `cameras:check-status` everyMinute
- `cameras:export` everyFiveMinutes (fallback, export otomatis saat ada perubahan status)

## Frontend Changes

### Hapus
- `probeSingleCamera()` — tidak ada fetch ke URL eksternal
- `probeOfflineCameras()` — tidak ada interval probing
- `scheduleCameraProbe()` — tidak dipakai
- Semua state: `_reachable`, `_isCooldownUntil`, `_adaptive_ready`

### Buat Baru
- `currentCameraStates = {}` — map id → 'online'|'offline'
- `pollLocalJson()` — fetch `/cameras.json`, deteksi transisi state
  - offline→online: initStream
  - online→offline: destroy HLS + UI offline

### Sederhanakan
- `initStream(cell, camera)` — pakai `camera.target_url` langsung
- `initObserver()` — visible + no manager → initStream langsung
- `refreshCameraData()` → ganti dengan `pollLocalJson()`

### Alur
```
Page Load → initObserver → visible & online → initStream(target_url)
Setiap 60s → pollLocalJson() → reaksi state change
```
