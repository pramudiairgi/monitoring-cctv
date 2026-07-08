#!/bin/bash

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN="live.polisihebat.org"
BRANCH="main"

R='\033[0;31m'; G='\033[0;32m'; Y='\033[1;33m'; B='\033[1;34m'; NC='\033[0m'
info()  { echo -e "${B}[${G}INFO${B}]${NC} $1"; }
ok()    { echo -e "${B}[${G} OK ${B}]${NC} $1"; }
warn()  { echo -e "${B}[${Y}WARN${B}]${NC} $1"; }
fail()  { echo -e "${B}[${R}FAIL${B}]${NC} $1"; exit 1; }

echo -e "\n${B}━━━ Monitoring CCTV — Quick Sync ━━━${NC}\n"

cd "$APP_DIR" || fail "Cannot cd to $APP_DIR"

info "Ambil alih kepemilikan file (sebelumnya www)..."
sudo chown -R "$USER:$USER" "$APP_DIR" || warn "Gagal chown ke user"

info "Pull code dari $BRANCH..."
git pull origin "$BRANCH" || fail "git pull failed"

info "Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction || fail "composer failed"

info "Frontend build..."
if [ -f package.json ]; then
  npm ci --no-ansi 2>/dev/null || npm install --no-ansi || warn "npm install failed"
  if [ -d node_modules ]; then
    npm run build --no-ansi || warn "npm run build failed"
  fi
fi

info "Migration..."
sudo -u www php artisan migrate --force || fail "migration failed"

info "Cache..."
sudo -u www php artisan config:cache 2>/dev/null || warn "config:cache failed"
sudo -u www php artisan route:cache 2>/dev/null || warn "route:cache failed"
sudo -u www php artisan view:cache 2>/dev/null || warn "view:cache failed"
sudo -u www php artisan cache:clear 2>/dev/null || warn "cache:clear failed"

info "Camera check & export..."
sudo -u www php artisan cameras:check-status 2>/dev/null || warn "check-status failed"
sudo -u www php artisan cameras:export 2>/dev/null || warn "export failed"

info "Restart services..."
sudo systemctl reload nginx 2>/dev/null || sudo systemctl restart nginx || warn "nginx restart failed"
sudo systemctl restart php8.4-fpm 2>/dev/null || warn "php-fpm restart failed"

info "Kembalikan kepemilikan ke www..."
sudo chown -R www:www "$APP_DIR" || warn "chown gagal"

echo -e "\n${G}━━━ Sync Complete ━━━${NC}"
echo -e "  ${B}Site:${NC}  https://$DOMAIN"
