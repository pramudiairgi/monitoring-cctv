# Deployment Guide

## Quick Start with Docker

### Prerequisites
- Docker and Docker Compose installed
- Git

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/pramudiairgi/monitoring-cctv.git
cd monitoring-cctv

# 2. Copy .env and configure
cp .env.example .env
# Edit .env with your database and service credentials

# 3. Generate APP_KEY
docker compose run --rm app php artisan key:generate

# 4. Start all services
docker compose up -d

# 5. Run migrations and seeders
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force

# 6. Access the application
# Admin panel: http://localhost
# API: http://localhost/api/*
```

### Docker Compose Services

| Service | Port | Description |
|---------|------|-------------|
| app | 9000 | PHP-FPM |
| nginx | 80/443 | Web server with SSL |
| db | 5432 | PostgreSQL 15 |
| redis | 6379 | Redis cache |
| worker | - | Queue worker |
| scheduler | - | Laravel scheduler |

## Manual Deployment (Without Docker)

### Prerequisites
- PHP 8.4+
- PostgreSQL 15+
- Redis 7+
- Node.js 20+
- Composer
- Nginx

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/pramudiairgi/monitoring-cctv.git
cd monitoring-cctv

# 2. Install PHP dependencies
composer install --optimize-autoloader --no-dev

# 3. Install Node dependencies
npm ci
npm run build

# 4. Configure environment
cp .env.example .env
# Edit .env with production values
php artisan key:generate

# 5. Set up database
sudo -u postgres createdb cctv_monitoring
php artisan migrate --force
php artisan db:seed --force

# 6. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 7. Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Configure Nginx
# Copy nginx.conf to /etc/nginx/conf.d/
# Enable SSL with Let's Encrypt

# 9. Set up queue worker
php artisan queue:work database --sleep=3 --tries=3

# 10. Set up scheduler
# Add to crontab: * * * * * cd /path/to/app && php artisan schedule:run
```

## GitHub Actions CI/CD

The repository includes automated CI/CD pipelines:

### CI Pipeline (`.github/workflows/ci.yml`)
- Runs on every push to `main`
- Executes tests with coverage
- Runs code linting (Pint)
- Performs security scanning
- Uploads coverage reports

### Deploy Pipeline (`.github/workflows/deploy.yml`)
- Runs on push to `main` or manual trigger
- Deploys to production environment
- Runs migrations and seeders
- Caches configuration and routes
- Requires `production` environment secret

### Required GitHub Secrets

| Secret | Description |
|--------|-------------|
| `APP_KEY` | Laravel application key |
| `DB_HOST` | Database host |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |
| `REDIS_HOST` | Redis host |
| `REDIS_PASSWORD` | Redis password |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |

## Environment Variables

### Required Variables

```env
APP_KEY=base64:...
APP_DEBUG=false
APP_ENV=production
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=cctv_monitoring
DB_USERNAME=your-db-user
DB_PASSWORD=your-strong-password
SESSION_ENCRYPT=true
SESSION_SAME_SITE=strict
QUEUE_CONNECTION=database
CACHE_STORE=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
```

### Optional Variables

```env
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
REDIS_CLIENT=phpredis
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
```

## Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] `SESSION_ENCRYPT=true`
- [ ] `SESSION_SAME_SITE=strict`
- [ ] Strong database password
- [ ] Strong Redis password
- [ ] HTTPS enabled
- [ ] SSL certificates configured
- [ ] `.env` file not in git
- [ ] File permissions set correctly
- [ ] Queue worker running
- [ ] Scheduler running
- [ ] Database migrations run
- [ ] All tests passing

## Monitoring

### Health Check
```bash
curl http://localhost/up
```

### Queue Status
```bash
php artisan queue:failed
php artisan queue:retry all
```

### Scheduler Status
```bash
php artisan schedule:list
```

### Logs
```bash
php artisan pail
# or
tail -f storage/logs/laravel.log
```

## Rollback

```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Rollback all migrations
php artisan migrate:reset

# Re-run migrations
php artisan migrate --force
```

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
   - Check `.env` APP_KEY is set
   - Check storage permissions
   - Check `storage/logs/laravel.log`

2. **Database Connection Failed**
   - Verify DB credentials in `.env`
   - Check PostgreSQL is running
   - Verify database exists

3. **Queue Not Processing**
   - Check queue worker is running: `php artisan queue:work`
   - Check `QUEUE_CONNECTION` in `.env`
   - Check `php artisan queue:failed`

4. **Scheduler Not Running**
   - Check crontab: `crontab -l`
   - Verify scheduler entry exists

5. **Permission Denied**
   - Run: `sudo chown -R www-data:www-data storage bootstrap/cache`
   - Run: `sudo chmod -R 775 storage bootstrap/cache`
