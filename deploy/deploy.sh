#!/bin/bash
set -e

echo "=== Monitoring CCTV Deployment ==="

APP_DIR="/var/www/monitoring-cctv"
REPO_URL="your-repo-url.git"
BRANCH="main"

# 1. Install dependencies
echo "[1/9] Installing system dependencies..."
sudo apt-get update
sudo apt-get install -y php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl nginx supervisor postgresql

# 2. Create app directory
echo "[2/9] Setting up application..."
sudo mkdir -p $APP_DIR
cd $APP_DIR

# 3. Clone/pull code
if [ -d ".git" ]; then
    git pull origin $BRANCH
else
    sudo git clone -b $BRANCH $REPO_URL .
fi
sudo chown -R www-data:www-data $APP_DIR

# 4. Install PHP dependencies
echo "[4/9] Installing composer dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# 5. Build frontend assets
echo "[5/9] Building frontend assets..."
sudo -u www-data npm ci --production
sudo -u www-data npm run build

# 6. Environment setup
echo "[6/9] Setting up environment..."
if [ ! -f .env ]; then
    sudo -u www-data cp .env.example .env
    sudo -u www-data php artisan key:generate
    echo ">>> Edit .env with your database credentials <<<"
fi

# 7. Database
echo "[7/9] Running migrations..."
sudo -u www-data php artisan migrate --force

# 8. Cache & initial probe
echo "[8/9] Clearing cache and running initial probe..."
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan cameras:check-status
sudo -u www-data php artisan cameras:export

# 9. Services
echo "[9/9] Configuring services..."
sudo cp $APP_DIR/deploy/nginx.conf /etc/nginx/sites-available/monitoring-cctv
sudo ln -sf /etc/nginx/sites-available/monitoring-cctv /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo cp $APP_DIR/deploy/supervisor.conf /etc/supervisor/conf.d/monitoring-queue.conf

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart laravel-schedule
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm

echo "=== Deployment complete ==="
echo "Access: http://your-server-ip"
echo "Admin: http://your-server-ip/admin"
