#!/bin/bash
set -e

echo "=== Monitoring CCTV Deployment ==="

APP_DIR="/var/www/monitoring-cctv"
REPO_URL="https://github.com/pramudiairgi/monitoring-cctv"
BRANCH="main"

DOMAIN="live.polisihebat.org"

# 1. Install dependencies
echo "[1/9] Installing system dependencies..."
sudo apt-get update
sudo apt-get install -y php8.4-fpm php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl nginx supervisor postgresql certbot python3-certbot-nginx

# 2. Create app directory
echo "[2/9] Setting up application..."
sudo mkdir -p $APP_DIR
sudo chown -R $USER:$USER $APP_DIR
cd $APP_DIR

# 3. Clone/pull code
if [ -d ".git" ]; then
    git pull origin $BRANCH
else
    git clone -b $BRANCH $REPO_URL .
fi

# 4. Setup PostgreSQL
echo "[3/9] Setting up database..."
sudo -u postgres psql -c "CREATE DATABASE cctv_monitoring;" 2>/dev/null || true
DB_PASSWORD=$(openssl rand -base64 24)
sudo -u postgres psql -c "ALTER USER postgres PASSWORD '${DB_PASSWORD}';"
sudo sed -i 's/^#port = 5432/port = 5431/' /etc/postgresql/*/main/postgresql.conf 2>/dev/null || true
sudo systemctl restart postgresql

# 5. Environment setup
echo "[4/9] Setting up environment..."
if [ ! -f .env ]; then
    cp deploy/.env.production .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
    php artisan key:generate
fi

# 6. Install PHP dependencies
echo "[5/9] Installing composer dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# 7. Build frontend assets
echo "[6/9] Building frontend assets..."
sudo -u www-data npm ci
sudo -u www-data npm run build

# 8. Set ownership (before artisan commands)
echo "[7/9] Setting permissions..."
sudo chown -R www-data:www-data $APP_DIR

# 9. Database migration
echo "[8/9] Running migrations and initializing..."
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan cameras:check-status
sudo -u www-data php artisan cameras:export

# 10. Services
echo "[9/9] Configuring services..."
sudo cp $APP_DIR/deploy/nginx.conf /etc/nginx/sites-available/$DOMAIN
sudo ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo cp $APP_DIR/deploy/supervisor.conf /etc/supervisor/conf.d/monitoring-queue.conf

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-schedule
sudo systemctl restart nginx
sudo systemctl restart php8.4-fpm

# SSL via Let's Encrypt
sudo certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN || echo "SSL skipped (run manually)"

echo "=== Deployment complete ==="
echo "Access: https://$DOMAIN"
echo "Admin: https://$DOMAIN/admin"
