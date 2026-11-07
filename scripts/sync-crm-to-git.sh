#!/bin/bash

# =========================================================================
# Vinttro SuiteCRM: Local Docker to Git Sync Script
# Path: scripts/sync-crm-to-git.sh
# =========================================================================

set -e

# --- 1. CONFIGURATION & PATH DETECTION ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Container name (defaults to suitecrm_web if not explicitly set)
CONTAINER_NAME="${SUITECRM_CONTAINER:-suitecrm_web}"

echo "================================================================="
echo "🔄 Starting Sync: Local Docker CRM -> Git Repository"
echo "================================================================="
echo "📁 Git Repo Root: $REPO_ROOT"

# --- 2. VERIFY DOCKER CONTAINER IS RUNNING ---
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "⚠️  Container '$CONTAINER_NAME' not found by exact name. Searching active containers..."
    DETECTED=$(docker ps --filter "name=suitecrm" --format "{{.Names}}" | head -n 1)
    if [ -n "$DETECTED" ]; then
        CONTAINER_NAME="$DETECTED"
        echo "✅ Detected active CRM container: $CONTAINER_NAME"
    else
        echo "❌ ERROR: No active SuiteCRM container found running in Docker."
        exit 1
    fi
fi

# --- 3. DYNAMICALLY DETECT CRM PATH INSIDE CONTAINER ---
echo "🔍 Detecting SuiteCRM root path inside container..."
CRM_PATH=$(docker exec "$CONTAINER_NAME" bash -c '
    for candidate in /var/www/suitecrm /var/www/html /var/www/html/suitecrm /bitnami/suitecrm /app; do
        if [ -d "$candidate/public/legacy" ]; then
            echo "$candidate"
            exit 0
        fi
    done
    # Fallback search if candidate directories do not match
    FOUND=$(find / -type f -name "entryPoint.php" 2>/dev/null | grep "public/legacy/include/entryPoint.php" | head -n 1)
    if [ -n "$FOUND" ]; then
        echo "${FOUND%/public/legacy/include/entryPoint.php}"
        exit 0
    fi
')

if [ -z "$CRM_PATH" ]; then
    echo "❌ ERROR: Could not locate a SuiteCRM 8 installation (public/legacy) inside '$CONTAINER_NAME'."
    echo "   Checking /var/www contents inside container:"
    docker exec "$CONTAINER_NAME" ls -la /var/www || true
    exit 1
fi

echo "✅ Located SuiteCRM directory inside container at: $CRM_PATH"

# Target directory in your Git repository
TARGET_LEGACY="$REPO_ROOT/suitecrm/public/legacy"
mkdir -p "$TARGET_LEGACY/modules"
mkdir -p "$TARGET_LEGACY/custom"
mkdir -p "$REPO_ROOT/suitecrm/public/dist/extensions"
mkdir -p "$REPO_ROOT/suitecrm/extensions"

# --- 4. SYNC COMPILED CUSTOM MODULES (modules/visp_*) ---
echo "📦 Syncing compiled visp_* runtime modules from container..."
VISP_DIRS=$(docker exec "$CONTAINER_NAME" bash -c "ls -d $CRM_PATH/public/legacy/modules/visp_* 2>/dev/null || true")

if [ -n "$VISP_DIRS" ]; then
    for dir in $VISP_DIRS; do
        mod_name=$(basename "$dir")
        echo "   -> Copying module: $mod_name"
        mkdir -p "$TARGET_LEGACY/modules/$mod_name"
        docker cp "$CONTAINER_NAME:$dir/." "$TARGET_LEGACY/modules/$mod_name/"
    done
else
    echo "   ℹ️  No compiled modules/visp_* directories found in container yet."
fi

# --- 5. SYNC CUSTOM OVERLAYS & MODULE BUILDER BLUEPRINTS ---
docker exec "$CONTAINER_NAME" tar -C "$CRM_PATH/public/legacy" \
    --exclude="custom/history" \
    --exclude="custom/backup" \
    --exclude="custom/blowfish" \
    --exclude="custom/working" \
    --exclude="*.save" \
    --exclude="custom/modulebuilder/builds" \
    -cf - custom | tar -C "$TARGET_LEGACY" -xf -

# --- 6. SYNC SUITECRM FRONTEND & EXTENSION ASSETS ---
echo "🎨 Syncing UI extensions and frontend bundles..."

if docker exec "$CONTAINER_NAME" test -d "$CRM_PATH/public/dist/extensions/vinttro-custom-ui"; then
    echo "   -> Copying public/dist/extensions/vinttro-custom-ui..."
    mkdir -p "$REPO_ROOT/suitecrm/public/dist/extensions/vinttro-custom-ui"
    docker cp "$CONTAINER_NAME:$CRM_PATH/public/dist/extensions/vinttro-custom-ui/." \
        "$REPO_ROOT/suitecrm/public/dist/extensions/vinttro-custom-ui/"
fi

if docker exec "$CONTAINER_NAME" test -d "$CRM_PATH/extensions"; then
    echo "   -> Copying /extensions/ directory..."
    docker cp "$CONTAINER_NAME:$CRM_PATH/extensions/." "$REPO_ROOT/suitecrm/extensions/"
fi

if docker exec "$CONTAINER_NAME" test -d "$CRM_PATH/vinttro2.0"; then
    echo "   -> Copying /vinttro2.0/ directory..."
    mkdir -p "$REPO_ROOT/suitecrm/vinttro2.0"
    docker cp "$CONTAINER_NAME:$CRM_PATH/vinttro2.0/." "$REPO_ROOT/suitecrm/vinttro2.0/"
fi

# --- 7. EXPORT STUDIO DATABASE FIELDS (fields_meta_data) ---
echo "🗄️  Exporting Studio custom fields (fields_meta_data) to Git..."
docker exec -i "$CONTAINER_NAME" php << EOF > "$REPO_ROOT/suitecrm/fields_meta_data.json"
<?php
\$_GET = array(); \$_POST = array(); \$_REQUEST = array(); \$_COOKIE = array();
\$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
if (file_exists("$CRM_PATH/public/legacy/include/entryPoint.php")) {
    chdir("$CRM_PATH/public/legacy");
    require_once("include/entryPoint.php");
    \$db = DBManagerFactory::getInstance();
    \$res = \$db->query("SELECT * FROM fields_meta_data WHERE deleted = 0");
    \$rows = array();
    while(\$row = \$db->fetchByAssoc(\$res)) {
        \$rows[] = \$row;
    }
    echo json_encode(\$rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
EOF

if [ -s "$REPO_ROOT/suitecrm/fields_meta_data.json" ]; then
    count=$(grep -c '"id":' "$REPO_ROOT/suitecrm/fields_meta_data.json" || true)
    echo "   ✅ Successfully exported $count custom studio field definitions to suitecrm/fields_meta_data.json"
else
    echo "   ℹ️  No custom fields_meta_data records exported (or table was empty)."
    rm -f "$REPO_ROOT/suitecrm/fields_meta_data.json"
fi

# --- 8. FIX HOST PERMISSIONS ---
if [ -n "$SUDO_USER" ]; then
    chown -R "$SUDO_USER":"$SUDO_USER" "$REPO_ROOT/suitecrm"
fi

echo "================================================================="
echo "🎉 Sync Complete! Current Git status for SuiteCRM:"
echo "================================================================="
cd "$REPO_ROOT"
git status --short suitecrm/
