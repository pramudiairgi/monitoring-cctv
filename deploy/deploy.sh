#!/bin/bash

# ---- Config ----
APP_DIR="/var/www/monitoring-cctv"
REPO_URL="https://github.com/pramudiairgi/monitoring-cctv"
BRANCH="main"
DOMAIN="live.polisihebat.org"

# ---- Colors ----
R='\033[0;31m'; G='\033[0;32m'; Y='\033[1;33m'; B='\033[1;34m'; NC='\033[0m'

# ---- Helpers ----
info()  { echo -e "${B}[${G}INFO${B}]${NC} $1"; }
ok()    { echo -e "${B}[${G} OK ${B}]${NC} $1"; }
warn()  { echo -e "${B}[${Y}WARN${B}]${NC} $1"; }
fail()  { echo -e "${B}[${R}FAIL${B}]${NC} $1"; exit 1; }
skip()  { echo -e "${B}[${Y}SKIP${B}]${NC} $1"; }

require() {
  command -v "$1" >/dev/null 2>&1 || fail "Required: $1"
}

retry() {
  local max=$1 delay=2 n=1; shift
  until "$@"; do
    [ $n -ge $max ] && return 1
    warn "Retry $n/$max in ${delay}s..."
    sleep "$delay"
    n=$((n+1)); delay=$((delay*2))
  done
  return 0
}

# ---- Pre-flight ----
info "Pre-flight checks..."
require sudo
require git
require openssl
require sed
require systemctl

# Check running as root-capable user
if ! sudo -n true 2>/dev/null; then
  fail "User does not have passwordless sudo access"
fi

# ---- Main ----
echo -e "\n${B}━━━ Monitoring CCTV — VPS Deployment ━━━${NC}\n"

# ──────────────────────────── [1/10] System Dependencies ────────────────────────────
info "[1/10] Installing system dependencies..."

# Add PHP 8.4 PPA (idempotent — Ubuntu 24.04 ships PHP 8.3 by default)
info "Ensuring PHP 8.4 PPA is available..."
sudo apt-get install -y software-properties-common 2>/dev/null || true
sudo add-apt-repository -y ppa:ondrej/php 2>/dev/null \
  || warn "Cannot add ondrej/php PPA — PHP 8.4 may not be in default repos"

retry 3 sudo apt-get update \
  || warn "apt-get update failed — proceeding with cached indexes"

# Install Node.js 22 from NodeSource (not in default repos)
if ! command -v node &>/dev/null || [ "$(node --version | cut -d'.' -f1 | tr -d 'v')" -lt 22 ]; then
  info "Installing Node.js 22 from NodeSource..."
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - 2>/dev/null \
    || warn "NodeSource setup failed"
fi

retry 3 sudo apt-get install -y \
  php8.4-fpm php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  php8.4-bcmath php8.4-intl nginx supervisor postgresql certbot python3-certbot-nginx nodejs \
  || fail "Failed to install system packages"

ok "System dependencies installed"

# ──────────────────────────── [2/10] Application Directory ────────────────────────────
info "[2/10] Setting up application directory..."

sudo mkdir -p "$APP_DIR" || fail "Cannot create $APP_DIR"
sudo chown -R "$USER:$USER" "$APP_DIR" || fail "Cannot chown $APP_DIR"
cd "$APP_DIR" || fail "Cannot cd to $APP_DIR"

ok "Application directory ready at $APP_DIR"

# ──────────────────────────── [3/10] Clone / Pull ────────────────────────────
info "[3/10] Fetching code from $REPO_URL ($BRANCH)..."

if [ -d ".git" ]; then
  retry 3 git pull origin "$BRANCH" || warn "git pull failed — using existing code"
else
  retry 3 git clone -b "$BRANCH" "$REPO_URL" . || fail "Cannot clone repository"
fi

ok "Code fetched"

# ──────────────────────────── [4/10] PostgreSQL Setup ────────────────────────────
info "[4/10] Setting up PostgreSQL..."

# Ensure PostgreSQL is running
sudo systemctl start postgresql || fail "Cannot start PostgreSQL"

# Set custom port
PG_CONF=$(sudo find /etc/postgresql -name postgresql.conf 2>/dev/null | head -1)
if [ -n "$PG_CONF" ]; then
  sudo sed -i 's/^port\s*=\s*5432/port = 5431/' "$PG_CONF" 2>/dev/null || true
  sudo sed -i 's/^#port\s*=\s*5432/port = 5431/' "$PG_CONF" 2>/dev/null || true
  sudo grep -q '^port = 5431' "$PG_CONF" || echo "port = 5431" | sudo tee -a "$PG_CONF" >/dev/null
  sudo systemctl restart postgresql || warn "PostgreSQL restart failed"
fi

# Create database (idempotent)
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='cctv_monitoring'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE DATABASE cctv_monitoring" \
  || warn "Cannot create database (may already exist)"

# Generate and set DB password
DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9')
sudo -u postgres psql -c "ALTER USER postgres PASSWORD '${DB_PASSWORD}'" \
  || fail "Cannot set PostgreSQL password"

# Ensure pg_hba.conf allows md5 for local TCP
PG_HBA=$(sudo find /etc/postgresql -name pg_hba.conf 2>/dev/null | head -1)
if [ -n "$PG_HBA" ]; then
  sudo sed -i 's|^local\s\+all\s\+all\s\+peer|local   all   all   md5|' "$PG_HBA"
  sudo sed -i 's|^host\s\+all\s\+all\s\+127\.0\.0\.1/32\s\+scram-sha-256|host   all   all   127.0.0.1/32   md5|' "$PG_HBA"
  sudo systemctl restart postgresql || warn "PostgreSQL restart (2) failed"
fi

ok "PostgreSQL ready — database 'cctv_monitoring' on port 5431"

# ──────────────────────────── [5/10] Environment Setup ────────────────────────────
info "[5/10] Setting up environment..."

if [ ! -f .env ]; then
  [ -f deploy/.env.production ] || fail "deploy/.env.production not found"
  cp deploy/.env.production .env || fail "Cannot copy .env.production"
  sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
  php artisan key:generate || fail "Cannot generate APP_KEY"
  ok ".env created with random DB password and APP_KEY"
else
  skip ".env already exists — leaving unchanged"
fi

# ──────────────────────────── [6/10] Composer Install ────────────────────────────
info "[6/10] Installing PHP dependencies..."

require composer
retry 3 composer install \
  --no-dev --optimize-autoloader --no-interaction --no-ansi \
  || fail "Composer install failed"

[ -f vendor/autoload.php ] || fail "vendor/autoload.php not found after install"

ok "Composer dependencies installed"

# ──────────────────────────── [7/10] Frontend Build ────────────────────────────
info "[7/10] Building frontend assets..."

if [ -f package.json ]; then
  if [ ! -d node_modules ]; then
    retry 3 npm ci --no-ansi \
      || warn "npm ci failed — trying npm install"
    [ ! -d node_modules ] && retry 3 npm install --no-ansi \
      || warn "npm install also failed"
  else
    skip "node_modules exists — skipping npm ci"
  fi

  if [ -d node_modules ]; then
    npm run build --no-ansi \
      || warn "npm run build failed — check for JS/CSS errors"
  else
    warn "node_modules not available — frontend build skipped"
  fi
else
  warn "No package.json found — frontend build skipped"
fi

ok "Frontend assets processed"

# ──────────────────────────── [8/10] Permissions ────────────────────────────
info "[8/10] Setting permissions..."

sudo chown -R www-data:www-data "$APP_DIR" \
  || fail "Cannot chown $APP_DIR"

ok "Permissions set"

# ──────────────────────────── [9/10] Database + App Init ────────────────────────────
info "[9/10] Running database migration and application init..."

# Migration (CRITICAL — stop on failure)
sudo -u www-data php artisan migrate --force \
  || fail "Migration failed — check database connection"

# Post-migration steps (non-critical — warn on failure)
sudo -u www-data php artisan storage:link 2>/dev/null \
  || warn "storage:link failed (link may already exist)"

sudo -u www-data php artisan config:cache 2>/dev/null \
  || warn "config:cache failed"

sudo -u www-data php artisan route:cache 2>/dev/null \
  || warn "route:cache failed"

sudo -u www-data php artisan view:cache 2>/dev/null \
  || warn "view:cache failed"

sudo -u www-data php artisan cache:clear 2>/dev/null \
  || warn "cache:clear failed"

sudo -u www-data php artisan cameras:check-status 2>/dev/null \
  || warn "Initial camera probe failed (expected if no cameras yet)"

sudo -u www-data php artisan cameras:export 2>/dev/null \
  || warn "Initial camera export failed"

ok "Database migrated and application initialized"

# ──────────────────────────── [10/10] Services ────────────────────────────
info "[10/10] Configuring nginx, supervisor, and SSL..."

# Nginx
sudo cp "$APP_DIR/deploy/nginx.conf" "/etc/nginx/sites-available/$DOMAIN" \
  || fail "Cannot copy nginx config"
sudo ln -sf "/etc/nginx/sites-available/$DOMAIN" /etc/nginx/sites-enabled/ \
  || warn "Cannot symlink nginx config"
sudo rm -f /etc/nginx/sites-enabled/default

# Test nginx config before restart
sudo nginx -t 2>/dev/null \
  || fail "Nginx config test failed — check deploy/nginx.conf"

sudo systemctl restart nginx || warn "Nginx restart failed"
ok "Nginx configured"

# Supervisor
sudo cp "$APP_DIR/deploy/supervisor.conf" /etc/supervisor/conf.d/monitoring-queue.conf \
  || warn "Cannot copy supervisor config"

sudo supervisorctl reread 2>/dev/null || true
sudo supervisorctl update 2>/dev/null || true
sudo supervisorctl start laravel-schedule 2>/dev/null \
  || warn "Supervisor: laravel-schedule start failed (may need manual start)"

ok "Supervisor configured"

# PHP-FPM
sudo systemctl restart php8.4-fpm || warn "php8.4-fpm restart failed"
ok "PHP-FPM restarted"

# SSL (non-critical)
if command -v certbot &>/dev/null; then
  sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos \
    --email "admin@$DOMAIN" 2>/dev/null \
    || warn "SSL certificate request failed — run manually: sudo certbot --nginx -d $DOMAIN"
fi

# ──────────────────────────── Final ────────────────────────────
echo -e "\n${G}━━━ Deployment Complete ━━━${NC}"
echo -e "  ${B}Site:${NC}  https://$DOMAIN"
echo -e "  ${B}Admin:${NC} https://$DOMAIN/admin"
echo -e "  ${B}Dir:${NC}   $APP_DIR"
echo -e ""
echo -e "  ${Y}Post-deploy:${NC}"
echo -e "  - Create admin user: php artisan make:filament-user"
echo -e "  - Monitor queue:    supervisorctl status"
echo -e "  - Monitor logs:     tail -f storage/logs/laravel.log"
echo -e ""
