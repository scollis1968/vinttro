#!/bin/bash

# ----------------------------------------------------------------
# tip :- run the following command to execute this script and see the logs in real-time: 
#    journalctl -u webhook -f
# ----------------------------------------------------------------

# --- CONFIGURATION ---

UAT_IP="10.154.0.3"

UAT_USER="webhook"

UAT_PATH="/var/www/wordpress"

PROD_PATH="/var/www/wordpress"

UAT_URL="https://uat.vinttro.co.uk"

PROD_URL="https://www.vinttro.co.uk"

LOG_FILE="/var/log/v-sync.log"

# Use the key we just moved

KEY="/var/www/.ssh/id_ed25519"



# Security: Tell SSH not to prompt for input and where to store host keys

SSH_OPTS="-i $KEY -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=30"

echo "🚀 Starting Secure Sync (as www-data)..."

# 1. Sync Files

# This will now work perfectly because www-data owns the destination folders

rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/uploads/ $PROD_PATH/wp-content/uploads/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/plugins/ $PROD_PATH/wp-content/plugins/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/themes/ $PROD_PATH/wp-content/themes/

# 1. Sync Files
# We use --no-p --no-g --no-o to stop preserving UAT ownership
# and --chmod to force correct permissions so sed can work later.
#rsync -avz --no-p --no-g --no-o --chmod=D2775,F664 -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/uploads/ $PROD_PATH/wp-content/uploads/
#rsync -avz --no-p --no-g --no-o --chmod=D2775,F664 -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/plugins/ $PROD_PATH/wp-content/plugins/
#rsync -avz --no-p --no-g --no-o --chmod=D2775,F664 -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/wp-content/themes/ $PROD_PATH/wp-content/themes/

# 2. Database Export and Transfer
echo "2. 🗂️ Database Transfer"
# ssh $SSH_OPTS $UAT_USER@$UAT_IP "wp db export --path=$UAT_PATH /tmp/uat_dump.sql"
ssh $SSH_OPTS $UAT_USER@$UAT_IP "wp db export --path=$UAT_PATH --exclude_tables=wp_users,wp_usermeta /tmp/uat_dump.sql"
scp $SSH_OPTS $UAT_USER@$UAT_IP:/tmp/uat_dump.sql /tmp/uat_dump.sql



# 3. Import and Replace
echo "3.📂 Import and Replace ..."
cd $PROD_PATH
wp db import /tmp/uat_dump.sql

wp search-replace "https://wwww.vinttro.co.uk" "https://www.vinttro.co.uk" --all-tables
wp search-replace "uat.vinttro.co.uk" "www.vinttro.co.uk" --all-tables
wp search-replace "$UAT_URL" "$PROD_URL" --all-tables
wp search-replace "uat.vinttro.co.uk" "www.vinttro.co.uk" --all-tables
wp search-replace ".uat@" "@" --all-tables


# --- THE FIX FOR YOUR FONT ISSUE ---
echo "3.5📂 Updating URLs inside CSS and JS files..."
# This finds all css/js/map files in wp-content and replaces the UAT URL with the PROD URL
find $PROD_PATH/wp-content -type f \( -name "*.css" -o -name "*.js" -o -name "*.map" \) -exec sed -i "s|$UAT_URL|$PROD_URL|g" {} +
find $PROD_PATH/wp-content -type f \( -name "*.css" -o -name "*.js" -o -name "*.map" -o -name "*.json" \) -exec sed -i "s|uat.vinttro.co.uk|www.vinttro.co.uk|g" {} +
#
# Corrected Elementor Check (checks if plugin is active instead of post-type)
if wp plugin is-active elementor --allow-root --path=$PROD_PATH; then
    log "🎨 Regenerating Elementor CSS..."
    wp elementor flush-css --allow-root --path=$PROD_PATH
fi

# 4 Production-Specific Sanitization
echo "4. 🛠️ Hardening Production Settings..."



# Force the Password Protection status to 0 (Disabled)

# We use wp option update. If it's already 0, it does nothing.

wp option update password_protected_status 0 --path=$PROD_PATH --allow-root



# Ensure Search Engines can crawl (1 = Public)

wp option update blog_public 1 --path=$PROD_PATH --allow-root



# CRITICAL: If you have a persistent cache (Redis/Memcached), 

# the database might be 0 but the site STILL shows the lock.

wp cache flush --path=$PROD_PATH --allow-root



echo "✅ Production site unlocked and public."



# Fix any lingering typos from the migration (like the wwww issue)


# In v-sync.sh
echo "🔍 Performing deep domain replacement..."
#wp search-replace "uat.vinttro.co.uk" "www.vinttro.co.uk" --all-tables --precise --path=$PROD_PATH --allow-root


# Ensure the SuiteCRM API Endpoint is pointing to PROD and not UAT

# Replace 'your_crm_option_name' with the actual option name used by your plugin

# wp option update your_crm_option_name "https://prodcrm.vinttro.co.uk"





# 5. Cleanup

rm -f /tmp/uat_dump.sql

ssh $SSH_OPTS $UAT_USER@$UAT_IP "rm -f /tmp/uat_dump.sql"

wp cache flush

sudo -u www-data wp option update tribe_events_calendar_slug "events" --path=/var/www/wordpress

sudo -u www-data wp option update tribe_events_single_event_slug "event" --path=/var/www/wordpress

wp rewrite flush --path=$PROD_PATH



echo "✅ Secure Sync Complete!"