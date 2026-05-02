#!/bin/bash



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


# 2. Database Transfer

# No --allow-root needed, as we are running as the web user

ssh $SSH_OPTS $UAT_USER@$UAT_IP "wp db export --path=$UAT_PATH /tmp/uat_dump.sql"

scp $SSH_OPTS $UAT_USER@$UAT_IP:/tmp/uat_dump.sql /tmp/uat_dump.sql



# 3. Import and Replace
log "3. Import and Replace ..."

cd $PROD_PATH

wp db import /tmp/uat_dump.sql

wp search-replace "$UAT_URL" "$PROD_URL" --all-tables

wp search-replace ".uat@" "@" --all-tables





# 4 Production-Specific Sanitization

echo "🛠️ Hardening Production Settings..."



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

wp search-replace "https://wwww.vinttro.co.uk" "https://www.vinttro.co.uk" --all-tables
wp search-replace "uat.vinttro.co.uk" "www.vinttro.co.uk" --all-tables

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

# 10. SuitCRM - CSS an styles
echo "10. SuitCRM - CSS and styles"

UAT_CRM_PATH="/var/www/suitecrm"
PROD_CRM_PATH="/var/www/suitecrm"

rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_CRM_PATH/extensions/vinttro-custom-ui/ $PROD_CRM_PATH/extensions/vinttro-custom-ui/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_CRM_PATH/public/dist/extensions/ $PROD_CRM_PATH/public/dist/extensions/

echo "✅ Secure Sync Complete!"