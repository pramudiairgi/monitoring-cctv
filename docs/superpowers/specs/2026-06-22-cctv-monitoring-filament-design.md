# CCTV Monitoring with Filament Admin Panel

**Date:** 2026-06-22  
**Status:** Approved  
**Scope:** Laravel + Filament backend for camera management + frontend monitoring page

## Summary

Sistem monitoring CCTV dengan admin panel Filament untuk mengelola camera (activate/deactivate), kategori, dan data lokasi. Frontend monitoring disajikan sebagai halaman dalam Laravel app yang sama.

## Requirements

### Functional

1. **Camera Management (Filament)**
    - CRUD cameras: name, stream_url, category, location, status
    - Toggle status online/offline (activate/deactivate)
    - Table view with category relation, status badge, search, filter

2. **Category Management (Filament)**
    - CRUD categories: name, slug (auto-generated)
    - Show camera count per category

3. **Auto JSON Export**
    - Generate `storage/app/public/cameras.json` on every Camera/Category save or delete
    - Structure: `{ cameras: [...], categories: [...] }`
    - Manual export command: `php artisan cameras:export`

4. **Frontend Monitoring Page**
    - Route `GET /` — reads from `cameras.json`
    - HLS playback via hls.js CDN
    - Fullscreen on click (CSS position: fixed)
    - Filters: search, category, status
    - Auto-hide navbar after 2s
    - Responsive: 4 columns desktop, 2 mobile

5. **Auth**
    - Simple email + password (Filament default)
    - Seeded admin user via `php artisan make:filament-user`

### Non-Functional

- Laravel 13.x
- Filament 5.x
- SQLite for development, PostgreSQL for production
- Static frontend assets (Vite)

## Architecture

```
┌─────────────────────────────────────────────┐
│              Laravel Application             │
├─────────────────────────────────────────────┤
│                                              │
│  /admin/*  ──→  Filament Panel               │
│                    ├── CameraResource        │
│                    ├── CategoryResource      │
│                    └── Auth (simple)         │
│                                              │
│  /         ──→  Monitoring Frontend Page     │
│                    └── reads cameras.json    │
│                                              │
│  storage/app/public/cameras.json             │
│    └── auto-generated via Observers          │
│                                              │
└─────────────────────────────────────────────┘
```

## Database Schema

### cameras

| Column      | Type      | Notes                                    |
| ----------- | --------- | ---------------------------------------- |
| id          | bigint    | PK, auto-increment                       |
| name        | string    | Camera display name                      |
| stream_url  | string    | HLS stream URL                           |
| category_id | bigint    | FK → categories.id                       |
| location    | string    | Location text                            |
| status      | enum      | online, offline                          |
| order       | integer   | Display order (lower = first), default 0 |
| created_at  | timestamp |                                          |
| updated_at  | timestamp |                                          |

### categories

| Column     | Type      | Notes                    |
| ---------- | --------- | ------------------------ |
| id         | bigint    | PK, auto-increment       |
| name       | string    | Category name            |
| slug       | string    | URL-safe, auto-generated |
| created_at | timestamp |                          |
| updated_at | timestamp |                          |

### users

Filament default (id, name, email, password, timestamps, etc.)

## JSON Export Format

File: `storage/app/public/cameras.json`

```json
{
    "cameras": [
        {
            "id": 1,
            "name": "CCTV - TUGUMUDA",
            "stream_url": "https://livepantau.semarangkota.go.id/...",
            "category": "traffic",
            "location": "Barusari, Semarang",
            "status": "online"
        }
    ],
    "categories": [
        { "value": "traffic", "label": "Traffic" },
        { "value": "public_facility", "label": "Public Facility" }
    ]
}
```

Note: `category` field in cameras array = category slug (resolved from category_id relation).

````

## Filament Resources

### CameraResource

**Table columns:**
- Name
- Category (relation → name)
- Location
- Status (badge: green=online, red=offline)

**Form fields:**
- Name (TextInput, required)
- Stream URL (TextInput, url, required)
- Category (Select, relation)
- Location (TextInput, required)
- Status (Select: online/offline)
- Order (TextInput, number)

**Actions:**
- Edit, Delete
- Toggle Status (Filament table action — toggles status field and saves)

### CategoryResource

**Table columns:**
- Name
- Slug
- Cameras count (relation count)

**Form fields:**
- Name (TextInput, required)
- Slug (TextInput, auto-generated from name)

## Observers

### CameraObserver

```php
public function saved(Camera $camera): void
{
    (new CameraExport)->handle();
}

public function deleted(Camera $camera): void
{
    (new CameraExport)->handle();
}
````

### CategoryObserver

```php
public function saved(Category $category): void
{
    (new CameraExport)->handle();
}

public function deleted(Category $category): void
{
    (new CameraExport)->handle();
}
```

## Artisan Command

```php
// php artisan cameras:export
// Manually regenerate cameras.json
```

## Frontend Integration

### Route

```php
Route::get('/', [MonitoringController::class, 'index']);
```

### Controller

```php
class MonitoringController extends Controller
{
    public function index()
    {
        $data = json_decode(
            file_get_contents(storage_path('app/public/cameras.json')),
            true
        );
        return view('monitoring', $data);
    }
}
```

### Blade View

Port existing `index.html` template into `resources/views/monitoring.blade.php`:

- Replace `const CAMERAS = [...]` with `@json($cameras)`
- Replace `const CATEGORIES = [...]` with `@json($categories)`
- Keep existing CSS/JS structure
- Publish assets via Vite

## File Structure (New/Additions)

```
app/
├── Filament/
│   └── Resources/
│       ├── CameraResource.php
│       ├── CameraResource/Pages/
│       │   ├── ListCameras.php
│       │   ├── CreateCamera.php
│       │   └── EditCamera.php
│       ├── CategoryResource.php
│       └── CategoryResource/Pages/
│           ├── ListCategories.php
│           ├── CreateCategory.php
│           └── EditCategory.php
├── Models/
│   ├── Camera.php
│   └── Category.php
├── Observers/
│   ├── CameraObserver.php
│   └── CategoryObserver.php
├── Console/Commands/
│   └── CameraExportCommand.php
├── Http/Controllers/
│   └── MonitoringController.php
└── Database/
    └── Migrations/
        ├── create_categories_table.php
        └── create_cameras_table.php

resources/
└── views/
    └── monitoring.blade.php

storage/app/public/
└── cameras.json (generated)
```

## Success Criteria

1. ✅ `php artisan make:filament-user` creates admin login
2. ✅ `/admin` shows Filament panel with Camera & Category resources
3. ✅ Create/edit/delete camera in Filament
4. ✅ Toggle camera status online/offline
5. ✅ Create/edit/delete category in Filament
6. ✅ `cameras.json` auto-generates on any save/delete
7. ✅ `php artisan cameras:export` manually regenerates JSON
8. ✅ `/` shows monitoring grid with cameras from JSON
9. ✅ HLS streams play correctly
10. ✅ Click camera → fullscreen, click/ESC → back
11. ✅ Filters work (search, category, status)
12. ✅ Responsive (2 mobile, 4 desktop)
