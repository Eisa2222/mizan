#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────
# package.sh — يُنشئ أرشيف تثبيت جاهز للنقل إلى خادم الإنتاج.
# ──────────────────────────────────────────────────────────────
# Usage:
#   bash deploy/package.sh [version]
#
# المخرج: mizan-<version>.tar.gz في المجلد الحالي.
# ──────────────────────────────────────────────────────────────
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
VERSION="${1:-$(date +%Y%m%d-%H%M)}"
OUTPUT="$PROJECT_ROOT/mizan-${VERSION}.tar.gz"

BLUE=$'\033[0;34m'; GREEN=$'\033[0;32m'; NC=$'\033[0m'
log() { echo "${BLUE}▶${NC} $*"; }
ok()  { echo "${GREEN}✓${NC} $*"; }

cd "$PROJECT_ROOT"

log "Packaging Mizan v${VERSION}..."

# Build frontend assets first — they need to be in the package
log "Building frontend assets..."
npm install --silent
npm run build --silent
ok "Assets built"

# What to include / exclude. The result MUST be installable by install.sh
# without needing the .git history, node_modules, or the dev SQLite db.
log "Creating archive..."
tar -czf "$OUTPUT" \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.phpunit.cache' \
    --exclude='.env' \
    --exclude='.env.backup' \
    --exclude='.claude' \
    --exclude='storage/app/tessdata/*.traineddata' \
    --exclude='storage/app/private/*' \
    --exclude='storage/app/public/*' \
    --exclude='storage/app/extract_zip.php' \
    --exclude='storage/app/google-fonts.css' \
    --exclude='storage/app/download_fonts.php' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='storage/logs/*' \
    --exclude='database/database.sqlite' \
    --exclude='public/build/.vite' \
    --exclude='public/storage' \
    --exclude='mizan-*.tar.gz' \
    --transform "s,^,mizan/," \
    .

SIZE=$(du -h "$OUTPUT" | cut -f1)
ok "Archive created: $OUTPUT ($SIZE)"

echo
echo "═══════════════════════════════════════════════════════"
echo "  To deploy:"
echo "  ═══════════"
echo "  1) scp $OUTPUT user@server:/tmp/"
echo "  2) ssh user@server"
echo "  3) sudo tar -xzf /tmp/$(basename "$OUTPUT") -C /var/www/"
echo "  4) cd /var/www/mizan"
echo "  5) cp deploy/install.conf.example deploy/install.conf"
echo "  6) vi deploy/install.conf       # edit DOMAIN, DB_PASSWORD, etc."
echo "  7) sudo bash deploy/install.sh"
echo
echo "  See deploy/INSTALL-PROD.md for the full guide."
echo "═══════════════════════════════════════════════════════"
