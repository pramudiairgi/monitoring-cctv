# CCTV Monitoring

Real-time CCTV monitoring dashboard with HLS stream playback, camera health checks, and telemetry collection.

## Tech Stack

- **Backend:** Laravel 13 + PHP 8.4
- **Database:** PostgreSQL 15 (Docker)
- **Frontend:** Blade + Tailwind CSS 4 + Vite 8 + hls.js
- **Queue:** Database driver
- **Admin:** Filament 3

## Requirements

- PHP 8.4+
- PostgreSQL 15+
- Node.js 18+ (check `.nvmrc`)
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
  -e POSTGRES_USER=cctv \
  -e POSTGRES_PASSWORD=rahasia123 \
  -e POSTGRES_DB=cctv_db \
  -p 5432:5432 \
  postgres:15-alpine
```

## Environment

Copy `.env.example` to `.env` and configure:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cctv_db
DB_USERNAME=cctv
DB_PASSWORD=rahasia123

QUEUE_CONNECTION=database
```

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

| Table              | Description                            |
| ------------------ | -------------------------------------- |
| `cameras`          | CCTV camera configurations             |
| `categories`       | Camera categories                      |
| `stream_telemetry` | Stream health telemetry (6h retention) |
| `jobs`             | Queue jobs                             |
| `failed_jobs`      | Failed queue jobs                      |

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
