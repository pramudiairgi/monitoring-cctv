# CCTV Monitoring

Real-time CCTV monitoring dashboard with HLS stream playback, camera health checks, and telemetry collection.

## Tech Stack

- **Backend:** Laravel 13 + PHP 8.4
- **Database:** PostgreSQL 15 (Docker)
- **Frontend:** Blade + Tailwind CSS 4 + Vite 8 + hls.js
- **Queue:** Database driver
- **Admin:** Filament 5 (SPA mode, dark theme)

## Requirements

- PHP 8.4+
- PostgreSQL 15+
- Node.js 22+ (used by deploy script)
- Composer

## Quick Start

```bash
# Clone & setup
git clone <repo-url>
cd monitoring-cctv
composer setup          # install + migrate + npm build

# Start development
npm run dev:full        # server + queue + logs + vite
```

### npm Scripts

| Script               | Description                                    |
| -------------------- | ---------------------------------------------- |
| `npm run dev:full`   | Start all services (server, queue, logs, vite) |
| `npm run dev`        | Vite only                                      |
| `npm run dev:server` | PHP artisan serve                              |
| `npm run dev:queue`  | Queue worker                                   |
| `npm run dev:logs`   | Log viewer (pail)                              |
| `npm run build`      | Production build                               |

## Docker (PostgreSQL)

```bash
docker run -d \
  --name cctv_container \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=<secret> \
  -e POSTGRES_DB=cctv_monitoring \
  -p 5431:5432 \
  postgres:15-alpine
```

## Environment

Copy `.env.example` to `.env` and configure:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5431
DB_DATABASE=cctv_monitoring
DB_USERNAME=postgres
DB_PASSWORD=<secret>

QUEUE_CONNECTION=database
```

Production source of truth is `deploy/.env.production.example`
(`DB_PORT=5431`, `DB_DATABASE=cctv_monitoring`).

> Never commit real credentials. Use placeholders locally and a secret
> manager (e.g. HashiCorp Vault, AWS Secrets Manager, Docker secrets, or
> your platform's env/secret store) for production values.

## Features

### Monitoring dashboard (`/`)

- **Auto-play limit** per device type (desktop / mobile landscape / portrait),
  configured in admin under Playback Settings. `0` = unlimited.
- **Priority category** (default `patroli`) plays first when capped; staggered
  startup avoids thundering-herd on the camera server.
- **Manual play**: click ▶ on a standby cell to play inline beyond the cap
  (session-only, exempt from auto-trim); ⏹ stops it. Click a cell for
  fullscreen; `f` toggles grid fullscreen; arrows navigate; `Esc` exits.
- **Cell states**: playing video, `Memuat tayangan...` (loading),
  `Online — klik untuk putar` (standby), offline overlay. Live counter
  (`Live N/M`) in the navbar.
- Camera streams are HLS served directly by the camera server (no Laravel proxy).

### Admin panel (`/admin`)

- Roles: `admin` (full) and `operator` (manage cameras/categories/settings,
  no delete). Playback Settings page (auto-play limits, stagger, priority).
- Category slugs auto-generate from names and are normalized on save; slugs
  drive app logic (`patroli` = priority/adaptive/toast) — do not rename
  lightly. Sidebar collapsible on desktop, full-width content.

### Camera catalogue & categories

- `Live Patroli` (`patroli`) + `Monitoring – Lalin/Polsek/Kantor` sub-groups.
- `CameraSeeder` is **authoritative**: cameras missing from its list are
  **pruned (deleted)** on `db:seed`, and retired category slugs are removed.
  Status/order/`target_url` are create-only and never overwritten on re-seed.

## Artisan Commands

| Command                             | Description                                               | Schedule     |
| ----------------------------------- | --------------------------------------------------------- | ------------ |
| `cameras:check-status`              | Probe stream URLs (parallel), update camera status        | Every minute |
| `cameras:check-status --only=14,16` | Check specific cameras only                               | Manual       |
| `cameras:export`                    | Export cameras to JSON (called by check-status on change) | On demand    |
| `telemetry:prune --hours=6`         | Delete telemetry older than N hours                       | Every hour   |

## Schedule

Defined in `routes/console.php`:

```php
Schedule::command('cameras:check-status')->everyMinute()->withoutOverlapping();
Schedule::command('telemetry:prune --hours=6')->hourly();
```

## API Endpoints

| Method | Endpoint         | Description              |
| ------ | ---------------- | ------------------------ |
| GET    | `/`              | Monitoring dashboard     |
| GET    | `/cameras.json`  | Camera list (cached 60s) |
| POST   | `/api/telemetry` | Submit telemetry data    |
| GET    | `/up`            | Health check             |

## Database Schema

### Tables

| Table                 | Description                                         |
| --------------------- | --------------------------------------------------- |
| `cameras`             | CCTV camera configurations                          |
| `categories`          | Camera categories (flat; `patroli` drives priority) |
| `settings`            | Key-value app settings (playback, etc.)             |
| `users`               | Admin/operator accounts (`role`: admin/operator)    |
| `patrol_logs`         | Patrol activity log                                 |
| `stream_telemetry`    | Stream health telemetry (6h retention)              |
| `personal_access_tokens` | API tokens                                       |
| `permission_tables`   | Spatie permission tables (Shield)                   |
| `jobs`                | Queue jobs                                          |
| `failed_jobs`         | Failed queue jobs                                   |

## Production Deployment

### Supervisor

Queue workers and scheduler managed by Supervisor:

```ini
[program:monitoring-queue]
command=php /var/www/monitoring-cctv/artisan queue:work --sleep=3 --tries=3 --max-time=3600
numprocs=2

[program:laravel-schedule]
command=php /var/www/monitoring-cctv/artisan schedule:work
```

### Nginx

See `deploy/nginx.conf` for Nginx configuration with:

- Gzip compression
- Security headers
- Static asset caching (30 days)
- PHP-FPM configuration

### Deploy

```bash
bash deploy/deploy.sh
```

The script pulls `main`, rebuilds frontend assets (previous build is backed
up and restored if the build fails), migrates, seeds, rebuilds caches, and
restarts nginx + queue/scheduler workers. Auto-deploy also runs on every push
to `main` via `.github/workflows/deploy.yml` (requires `DEPLOY_HOST`,
`DEPLOY_USER`, `DEPLOY_SSH_KEY` secrets).

> **Back up the database first** (`pg_dump`) — releases may include
> migrations and catalogue reseeds. See handover notes if relaying to
> another operator.

### Rollback

```bash
cd /var/www/monitoring-cctv
git checkout <previous-commit> -- .  # or: git reset --hard <previous-commit>
sudo -u postgres psql cctv_monitoring < ~/backup-cctv-<DATE>.sql
bash deploy/deploy.sh
```

Production env template: `deploy/.env.production.example` (placeholders only —
`APP_ENV=production`, `APP_DEBUG=false`, `DB_PORT=5431`,
`DB_DATABASE=cctv_monitoring`).

Nightly backups: `deploy/backup-cron.example`. Log rotation:
`deploy/logrotate.example`.

## Project Structure

```
app/
├── Console/Commands/      # Artisan commands
├── Http/Controllers/      # API & web controllers
├── Jobs/                  # Queue jobs
├── Models/                # Eloquent models
└── Services/              # Business logic (CameraExport)
```

## License

MIT
