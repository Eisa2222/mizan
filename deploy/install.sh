#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────
# منصة ميزان — Production installer
# ──────────────────────────────────────────────────────────────
# Usage:
#   cp deploy/install.conf.example deploy/install.conf
#   # edit deploy/install.conf
#   sudo bash deploy/install.sh
#
# What it does:
#   1. Validates install.conf has all required fields
#   2. Installs system dependencies (php 8.3, composer, node, mysql, ...)
#   3. Generates .env from install.conf
#   4. Runs composer install, npm build, artisan migrate + seed
#   5. Writes nginx/apache vhost with the chosen domain
#   6. Obtains Let's Encrypt cert (if SSL_MODE=letsencrypt)
#   7. Downloads Tesseract Arabic data (if INSTALL_ARABIC_OCR=true)
#   8. Creates the initial SuperAdmin account
#
# Safe to re-run: every step is idempotent.
# ──────────────────────────────────────────────────────────────
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$SCRIPT_DIR/install.conf"

RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; BLUE=$'\033[0;34m'; NC=$'\033[0m'
log()  { echo "${BLUE}▶${NC} $*"; }
ok()   { echo "${GREEN}✓${NC} $*"; }
warn() { echo "${YELLOW}⚠${NC} $*"; }
die()  { echo "${RED}✗${NC} $*" >&2; exit 1; }

# ── 1) Load and validate config ────────────────────────────────
[[ -f "$CONFIG_FILE" ]] || die "Config file not found: $CONFIG_FILE
Copy the template: cp deploy/install.conf.example deploy/install.conf"

# shellcheck disable=SC1090
source "$CONFIG_FILE"

require_var() {
    local name="$1" value="${!1:-}"
    [[ -n "$value" ]] || die "Required config value is empty: $name (edit $CONFIG_FILE)"
}

log "Validating install.conf..."
for v in DOMAIN APP_URL APP_ENV APP_NAME DB_CONNECTION AI_PROVIDER \
         INITIAL_ADMIN_EMAIL INITIAL_ADMIN_PASSWORD INITIAL_ADMIN_NAME; do
    require_var "$v"
done

if [[ "$DB_CONNECTION" != "sqlite" ]]; then
    for v in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do require_var "$v"; done
    [[ "$DB_PASSWORD" != *"CHANGE_ME"* ]] || die "DB_PASSWORD is still the placeholder — set a real password."
fi

if [[ "$AI_PROVIDER" = "anthropic" ]]; then
    require_var ANTHROPIC_API_KEY
elif [[ "$AI_PROVIDER" = "ollama" ]]; then
    require_var OLLAMA_HOST
    require_var OLLAMA_MODEL
else
    die "AI_PROVIDER must be 'ollama' or 'anthropic' (got: $AI_PROVIDER)"
fi

[[ "$INITIAL_ADMIN_PASSWORD" != *"ChangeMe"* ]] || warn "INITIAL_ADMIN_PASSWORD still contains 'ChangeMe' — strongly recommend changing after first login."

ok "Config validated"

# ── 2) Require root for system changes ─────────────────────────
if [[ $EUID -ne 0 && "$APP_ENV" = "production" ]]; then
    die "Run as root (sudo) for production install — needs to write nginx conf + install packages."
fi

# ── 3) System packages (best-effort — supports apt) ────────────
install_system_packages() {
    if ! command -v apt-get >/dev/null 2>&1; then
        warn "apt-get not found — skipping system package install (assume you installed manually)."
        return
    fi

    log "Updating apt index..."
    apt-get update -y >/dev/null

    log "Installing system packages (php, web server, db, node)..."
    local pkgs=(
        php8.3 php8.3-fpm php8.3-cli php8.3-common
        php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip
        php8.3-gd php8.3-intl php8.3-bcmath
        composer
        poppler-utils
        curl ca-certificates gnupg
    )

    [[ "$DB_CONNECTION" = "mysql" ]] && pkgs+=(mysql-server php8.3-mysql)
    [[ "$DB_CONNECTION" = "pgsql" ]] && pkgs+=(postgresql php8.3-pgsql)
    [[ "$DB_CONNECTION" = "sqlite" ]] && pkgs+=(php8.3-sqlite3)
    [[ "$WEB_SERVER" = "nginx" ]] && pkgs+=(nginx)
    [[ "$WEB_SERVER" = "apache" ]] && pkgs+=(apache2 libapache2-mod-php8.3)
    [[ "$SSL_MODE" = "letsencrypt" ]] && pkgs+=(certbot python3-certbot-nginx)
    [[ "${INSTALL_ARABIC_OCR:-false}" = "true" ]] && pkgs+=(tesseract-ocr tesseract-ocr-ara)

    apt-get install -y --no-install-recommends "${pkgs[@]}" >/dev/null
    ok "System packages installed"

    # Node 22 LTS via NodeSource
    if ! command -v node >/dev/null 2>&1 || [[ "$(node -v 2>/dev/null | cut -c2- | cut -d. -f1)" -lt 22 ]]; then
        log "Installing Node.js 22 LTS..."
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null
        apt-get install -y nodejs >/dev/null
        ok "Node.js $(node -v) installed"
    fi
}

if [[ "$APP_ENV" = "production" ]]; then
    install_system_packages
else
    warn "APP_ENV=$APP_ENV — skipping system package install (dev mode)"
fi

# ── 4) Generate .env from template ─────────────────────────────
log "Generating .env from install.conf..."
cd "$PROJECT_ROOT"

# Generate APP_KEY if not carried from previous install
APP_KEY_VALUE=""
if [[ -f .env ]] && grep -q "^APP_KEY=base64:" .env; then
    APP_KEY_VALUE=$(grep "^APP_KEY=" .env | head -1 | cut -d= -f2-)
    ok "Preserving existing APP_KEY"
else
    APP_KEY_VALUE="base64:$(openssl rand -base64 32)"
    ok "Generated new APP_KEY"
fi

cat > .env <<ENVEOF
APP_NAME="${APP_NAME}"
APP_ENV=${APP_ENV}
APP_KEY=${APP_KEY_VALUE}
APP_DEBUG=$([ "$APP_ENV" = "production" ] && echo false || echo true)
APP_URL=${APP_URL}
APP_TIMEZONE=${APP_TIMEZONE:-Asia/Riyadh}
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ar_SA

LOG_CHANNEL=stack
LOG_LEVEL=$([ "$APP_ENV" = "production" ] && echo warning || echo debug)

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-mizaan}
DB_USERNAME=${DB_USERNAME:-mizaan_user}
DB_PASSWORD=${DB_PASSWORD:-}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SECURE_COOKIE=$([ "$APP_ENV" = "production" ] && echo true || echo false)
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_HOST=${MAIL_HOST:-}
MAIL_PORT=${MAIL_PORT:-587}
MAIL_USERNAME=${MAIL_USERNAME:-}
MAIL_PASSWORD=${MAIL_PASSWORD:-}
MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-tls}
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-no-reply@${DOMAIN}}"
MAIL_FROM_NAME="${MAIL_FROM_NAME:-${APP_NAME}}"

AI_PROVIDER=${AI_PROVIDER}
OLLAMA_HOST=${OLLAMA_HOST:-http://localhost:11434}
OLLAMA_MODEL=${OLLAMA_MODEL:-gemma3:27b}
ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY:-}
ANTHROPIC_MODEL=${ANTHROPIC_MODEL:-claude-sonnet-4-5}

ELASTICSEARCH_HOST=${ELASTICSEARCH_HOST:-}

VITE_APP_NAME="${APP_NAME}"
ENVEOF

chmod 640 .env
ok ".env generated (chmod 640)"

# ── 5) Composer + npm + build ──────────────────────────────────
log "Installing composer dependencies..."
if [[ "$APP_ENV" = "production" ]]; then
    composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
else
    composer install --no-interaction --prefer-dist
fi
ok "Composer packages installed"

log "Installing npm packages + building assets..."
npm install --silent
npm run build --silent
ok "Frontend assets built"

# ── 6) Storage + permissions ───────────────────────────────────
log "Linking storage + fixing permissions..."
php artisan storage:link 2>/dev/null || true

# Web server user (best-effort detection)
WEB_USER="www-data"
if ! id -u "$WEB_USER" >/dev/null 2>&1; then
    WEB_USER="$(ps -o user= -p 1 2>/dev/null | head -1)"
fi

chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
ok "Permissions set (owner: $WEB_USER)"

# ── 7) Database: create DB + run migrations ────────────────────
if [[ "$DB_CONNECTION" = "sqlite" ]]; then
    mkdir -p database
    [[ -f database/database.sqlite ]] || touch database/database.sqlite
    ok "SQLite file ready"
elif [[ "$DB_CONNECTION" = "mysql" ]] && command -v mysql >/dev/null 2>&1; then
    log "Ensuring MySQL database + user exist..."
    mysql -uroot <<MYSQL_EOF 2>/dev/null || warn "Couldn't create DB/user automatically — create them manually then re-run"
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
MYSQL_EOF
    ok "MySQL database '${DB_DATABASE}' ready"
fi

log "Running migrations..."
php artisan migrate --force --ansi
ok "Schema migrated"

log "Seeding knowledge base + default data..."
php artisan db:seed --force --ansi
ok "Seed complete"

# ── 8) Create the initial SuperAdmin ───────────────────────────
log "Creating initial SuperAdmin (${INITIAL_ADMIN_EMAIL})..."
php artisan tinker --execute="
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;

\$org = Organization::firstOrCreate(
    ['domain' => '${DOMAIN}'],
    ['name_ar' => 'المؤسسة الرئيسية', 'name_en' => 'Primary Organization']
);

User::updateOrCreate(
    ['email' => '${INITIAL_ADMIN_EMAIL}'],
    [
        'name' => '${INITIAL_ADMIN_NAME}',
        'password' => Hash::make('${INITIAL_ADMIN_PASSWORD}'),
        'org_id' => \$org->id,
        'role' => 'SuperAdmin',
        'email_verified_at' => now(),
    ]
);
echo 'admin ready' . PHP_EOL;
" 2>&1 | tail -3
ok "SuperAdmin ready"

# ── 9) Cache artisan config/routes/views ──────────────────────
if [[ "$APP_ENV" = "production" ]]; then
    log "Caching Laravel config/routes/views for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ok "Production caches warmed"
fi

# ── 10) Web server vhost ──────────────────────────────────────
if [[ "$APP_ENV" = "production" ]]; then
    if [[ "$WEB_SERVER" = "nginx" ]]; then
        VHOST_FILE="/etc/nginx/sites-available/mizan"
        ENABLED_FILE="/etc/nginx/sites-enabled/mizan"
        TEMPLATE="$SCRIPT_DIR/nginx.conf.template"
    else
        VHOST_FILE="/etc/apache2/sites-available/mizan.conf"
        ENABLED_FILE=""
        TEMPLATE="$SCRIPT_DIR/apache.conf.template"
    fi

    log "Writing $WEB_SERVER vhost to $VHOST_FILE..."
    sed -e "s|__DOMAIN__|${DOMAIN}|g" \
        -e "s|__PATH__|${PROJECT_ROOT}|g" \
        -e "s|__PHP_FPM_SOCK__|${PHP_FPM_SOCK:-/run/php/php8.3-fpm.sock}|g" \
        "$TEMPLATE" > "$VHOST_FILE"
    ok "vhost written"

    if [[ "$WEB_SERVER" = "nginx" ]]; then
        [[ -L "$ENABLED_FILE" ]] || ln -s "$VHOST_FILE" "$ENABLED_FILE"
        nginx -t && systemctl reload nginx
        ok "Nginx reloaded"
    else
        a2enmod rewrite headers ssl proxy_fcgi setenvif >/dev/null
        a2ensite mizan >/dev/null
        apache2ctl configtest && systemctl reload apache2
        ok "Apache reloaded"
    fi
fi

# ── 11) SSL via Let's Encrypt ─────────────────────────────────
if [[ "$APP_ENV" = "production" && "${SSL_MODE:-}" = "letsencrypt" ]]; then
    log "Requesting Let's Encrypt certificate for ${DOMAIN}..."
    require_var ADMIN_EMAIL
    if [[ "$WEB_SERVER" = "nginx" ]]; then
        certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect || \
            warn "certbot failed — you can retry manually: certbot --nginx -d ${DOMAIN}"
    else
        certbot --apache -d "${DOMAIN}" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect || \
            warn "certbot failed — you can retry manually: certbot --apache -d ${DOMAIN}"
    fi
fi

# ── 12) Optional: Arabic OCR data ─────────────────────────────
if [[ "${INSTALL_ARABIC_OCR:-false}" = "true" ]]; then
    TESSDATA_DIR="$PROJECT_ROOT/storage/app/tessdata"
    ARA_FILE="$TESSDATA_DIR/ara.traineddata"
    if [[ ! -f "$ARA_FILE" ]]; then
        log "Downloading Tesseract Arabic data (~13MB)..."
        mkdir -p "$TESSDATA_DIR"
        curl -sSL "https://github.com/tesseract-ocr/tessdata_best/raw/main/ara.traineddata" -o "$ARA_FILE"
        ok "Arabic tessdata installed"
    fi
fi

# ── 13) Queue worker systemd unit ─────────────────────────────
if [[ "$APP_ENV" = "production" && "${QUEUE_CONNECTION:-}" != "sync" ]]; then
    UNIT_FILE="/etc/systemd/system/mizan-queue.service"
    log "Installing queue worker service at $UNIT_FILE..."
    cat > "$UNIT_FILE" <<UNIT_EOF
[Unit]
Description=Mizan queue worker
After=network.target

[Service]
User=${WEB_USER}
Group=${WEB_USER}
WorkingDirectory=${PROJECT_ROOT}
ExecStart=/usr/bin/php ${PROJECT_ROOT}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT_EOF
    systemctl daemon-reload
    systemctl enable --now mizan-queue >/dev/null 2>&1 || warn "Could not start mizan-queue (check: systemctl status mizan-queue)"
    ok "Queue worker running"
fi

# ── 14) Schedule runner (cron) ────────────────────────────────
if [[ "$APP_ENV" = "production" ]]; then
    CRON_LINE="* * * * * cd $PROJECT_ROOT && php artisan schedule:run >> /dev/null 2>&1"
    if ! (crontab -l 2>/dev/null | grep -F "$PROJECT_ROOT/artisan schedule:run") >/dev/null; then
        log "Installing Laravel scheduler cron entry..."
        (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
        ok "Cron installed (runs every minute)"
    fi
fi

# ── 15) Summary ───────────────────────────────────────────────
echo
echo "════════════════════════════════════════════════════════════"
echo "${GREEN}✓ Installation complete${NC}"
echo "════════════════════════════════════════════════════════════"
echo
echo "  Domain:      ${APP_URL}"
echo "  Environment: ${APP_ENV}"
echo "  Admin email: ${INITIAL_ADMIN_EMAIL}"
echo "  Admin pass:  (set from install.conf — change after first login)"
echo
echo "  ${YELLOW}Security checklist:${NC}"
echo "  [ ] Change the admin password via /profile"
echo "  [ ] Review .env for any remaining placeholder values"
echo "  [ ] Confirm HTTPS redirect with: curl -I http://${DOMAIN}"
echo "  [ ] Check security headers at securityheaders.com/?q=${DOMAIN}"
echo
echo "  Logs:"
echo "    - App logs:   ${PROJECT_ROOT}/storage/logs/laravel.log"
echo "    - Web server: /var/log/${WEB_SERVER}/mizan.*.log"
echo "    - Queue:      journalctl -u mizan-queue -f"
echo
