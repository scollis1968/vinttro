#!/bin/bash

# ----------------------------------------------------------------
# tip :- run the following command to execute this script and see the logs in real-time: 
#      journalctl -u webhook -f
# ----------------------------------------------------------------

# --- CONFIGURATION ---
UAT_IP="10.154.0.3"
UAT_USER="webhook"
UAT_PATH="/var/www/wordpress"
PROD_PATH="/var/www/wordpress"
UAT_URL="https://uat.vinttro.co.uk"
PROD_URL="https://www.vinttro.co.uk"
LOG_FILE="/var/log/v-sync.log"
KEY="/var/www/.ssh/id_ed25519"

# Security: Tell SSH not to prompt for input and where to store host keys
SSH_OPTS="-i $KEY -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=30"

echo "🚀 Starting Secure Sync (as www-data)..."

# ==========================================
# 1. FILE SYNC
# ==========================================
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/uploads/ $PROD_PATH/wp-content/uploads/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/plugins/ $PROD_PATH/wp-content/plugins/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/themes/ $PROD_PATH/wp-content/themes/

# ==========================================
# 2. DATABASE IMPORT
# ==========================================
echo "2. 🗂️ Database Transfer"
ssh $SSH_OPTS $UAT_USER@$UAT_IP "wp db export --path=$UAT_PATH --exclude_tables=wp_users,wp_usermeta /tmp/uat_dump.sql"
scp $SSH_OPTS $UAT_USER@$UAT_IP:/tmp/uat_dump.sql /tmp/uat_dump.sql

echo "3.📂 Importing Database..."
cd $PROD_PATH
wp db import /tmp/uat_dump.sql

# ==========================================
# 3. GLOBAL SEARCH & REPLACE & CONFIG FIXES
# ==========================================
echo "🔍 Performing search and replace operations..."
wp search-replace "https://wwww.vinttro.co.uk" "https://www.vinttro.co.uk" --all-tables --path=$PROD_PATH
wp search-replace "uat.vinttro.co.uk" "www.vinttro.co.uk" --all-tables --path=$PROD_PATH
wp search-replace "$UAT_URL" "$PROD_URL" --all-tables --path=$PROD_PATH
wp search-replace ".uat@" "@" --all-tables --path=$PROD_PATH

echo "🔧 Fixing plugin-specific options..."
# Moving these up here ensures they don't break our hardening later
wp option update tribe_events_calendar_slug "events" --path=$PROD_PATH
wp option update tribe_events_single_event_slug "event" --path=$PROD_PATH

# ==========================================
# 4. ASSET UPDATES & REGENERATION
# ==========================================
echo "3.5📂 Updating URLs inside CSS and JS files..."
find $PROD_PATH/wp-content -type f \( -name "*.css" -o -name "*.js" -o -name "*.map" \) -exec sed -i "s|$UAT_URL|$PROD_URL|g" {} +
find $PROD_PATH/wp-content -type f \( -name "*.css" -o -name "*.js" -o -name "*.map" -o -name "*.json" \) -exec sed -i "s|uat.vinttro.co.uk|www.vinttro.co.uk|g" {} +

if wp plugin is-active elementor --path=$PROD_PATH; then
    echo "🎨 Regenerating Elementor CSS..."
    wp elementor flush-css --path=$PROD_PATH
fi

# ==========================================
# 5. PRODUCTION HARDENING (THE FINAL LIFELINE)
# ==========================================
echo "4. 🛠️ Hardening Production Settings & Unlocking Site..."

# 1. Force password protection to 0
wp option update password_protected_status 0 --path=$PROD_PATH

# 2. Force site public to search engines
wp option update blog_public 1 --path=$PROD_PATH

# 3. Flush rewrites now that ALL options are settled
wp rewrite flush --path=$PROD_PATH

# 4. CRITICAL CLOSING STEP: Flush only the local WordPress cache database slot 
# This guarantees that our '0' value is fresh and won't get overridden by stale cache write-backs
redis-cli -n 0 flushdb

echo "✅ Production site unlocked, public, and cache cleared."

# ==========================================
# 6. CLEANUP
# ==========================================
rm -f /tmp/uat_dump.sql
ssh $SSH_OPTS $UAT_USER@$UAT_IP "rm -f /tmp/uat_dump.sql"

echo "✅ Secure Sync Complete!"