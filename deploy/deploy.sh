#!/bin/bash
set -e

echo "=== Monitoring CCTV Deployment ==="

APP_DIR="/var/www/monitoring-cctv"
REPO_URL="your-repo-url.git"
BRANCH="main"

# 1. Install dependencies
echo "[1/7] Installing system dependencies..."
sudo apt-get update
sudo apt-get install -y php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl nginx supervisor postgresql

# 2. Create app directory
echo "[2/7] Setting up application..."
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
echo "[3/7] Installing composer dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# 5. Environment setup
echo "[4/7] Setting up environment..."
if [ ! -f .env ]; then
    sudo -u www-data cp .env.example .env
    sudo -u www-data php artisan key:generate
    echo ">>> Edit .env with your database credentials <<<"
fi

# 6. Database
echo "[5/7] Running migrations..."
sudo -u www-data php artisan migrate --force

# 7. Storage & cache
echo "[6/7] Setting up storage..."
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 8. Services
echo "[7/7] Configuring services..."
sudo cp $APP_DIR/deploy/nginx.conf /etc/nginx/sites-available/monitoring-cctv
sudo ln -sf /etc/nginx/sites-available/monitoring-cctv /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo cp $APP_DIR/deploy/supervisor.conf /etc/supervisor/conf.d/monitoring-queue.conf

sudo supervisorctl reread
sudo supervisorctl update
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm

echo "=== Deployment complete ==="
echo "Access: http://your-server-ip"
echo "Admin: http://your-server-ip/admin"
