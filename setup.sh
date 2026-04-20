#!/usr/bin/env bash
# ──────────────────────────────────────────────
# منصة ميزان — installer للـ Linux / macOS
# يتحقق من المتطلبات ويثبّت النظام في خطوة واحدة.
# ──────────────────────────────────────────────
set -euo pipefail

RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; BLUE=$'\033[0;34m'; NC=$'\033[0m'

log()  { echo "${BLUE}▶${NC} $*"; }
ok()   { echo "${GREEN}✓${NC} $*"; }
warn() { echo "${YELLOW}⚠${NC} $*"; }
die()  { echo "${RED}✗${NC} $*" >&2; exit 1; }

# 1) verify prerequisites
log "Checking prerequisites..."

command -v php >/dev/null 2>&1 || die "PHP is not installed. Need PHP 8.3+."
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
[[ "$(php -r 'echo version_compare(PHP_VERSION, "8.3.0", ">=") ? "1" : "0";')" = "1" ]] \
    || die "PHP version $PHP_VERSION is too old. Need 8.3+."
ok "PHP $PHP_VERSION"

command -v composer >/dev/null 2>&1 || die "Composer is not installed."
ok "Composer $(composer --version --no-ansi | head -1)"

command -v node >/dev/null 2>&1 || die "Node.js is not installed. Need 22.x LTS."
NODE_VERSION=$(node --version | sed 's/v//')
[[ "$(node -e 'process.stdout.write(process.versions.node.split(".")[0]>="22" ? "1" : "0")')" = "1" ]] \
    || warn "Node $NODE_VERSION detected; 22.x LTS is recommended."
ok "Node $NODE_VERSION"

command -v npm >/dev/null 2>&1 || die "npm is not installed."
ok "npm $(npm --version)"

# 2) .env
if [[ ! -f .env ]]; then
    log "Creating .env from .env.example..."
    cp .env.example .env
    ok ".env created"
else
    warn ".env already exists — skipping copy"
fi

# 3) install dependencies
log "Installing composer dependencies (this may take a minute)..."
composer install --no-interaction --prefer-dist
ok "composer packages installed"

log "Installing npm packages..."
npm install --silent
ok "npm packages installed"

# 4) app key
if ! grep -qE '^APP_KEY=base64:' .env; then
    log "Generating APP_KEY..."
    php artisan key:generate --ansi
    ok "APP_KEY set"
else
    ok "APP_KEY already set"
fi

# 5) database
DB_CONNECTION=$(grep -E '^DB_CONNECTION=' .env | cut -d'=' -f2)
if [[ "$DB_CONNECTION" = "sqlite" ]]; then
    mkdir -p database
    if [[ ! -f database/database.sqlite ]]; then
        log "Creating SQLite file..."
        touch database/database.sqlite
        ok "database/database.sqlite created"
    fi
fi

log "Running migrations..."
php artisan migrate --force --ansi
ok "Migrations applied"

log "Seeding default admin + GPC knowledge base..."
php artisan db:seed --force --ansi
ok "Seed complete"

# 6) storage link + build
log "Linking storage..."
php artisan storage:link 2>/dev/null || true
ok "Storage linked"

log "Building frontend assets..."
npm run build --silent
ok "Assets built"

# 7) summary
echo
echo "════════════════════════════════════════════════"
echo "${GREEN}✓ Installation complete${NC}"
echo "════════════════════════════════════════════════"
echo
echo "Default admin:"
echo "  Email:    admin@mizaan.local"
echo "  Password: Admin@123"
echo "${YELLOW}  → change it immediately after first login${NC}"
echo
echo "Next steps:"
echo "  1) Set ANTHROPIC_API_KEY in .env for AI features"
echo "  2) Start the stack:  composer dev"
echo "     or manually:"
echo "       php artisan serve"
echo "       php artisan queue:work   (second terminal)"
echo "       npm run dev              (third terminal)"
echo "  3) Open http://localhost:8000"
echo
echo "Optional — train AI on reference corpus:"
echo "  php artisan mizaan:train-from-folder /path/to/references"
echo
echo "See INSTALL.md + REQUIREMENTS.md for full docs."
