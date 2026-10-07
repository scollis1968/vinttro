#!/bin/bash
# sync-wp-uat-to-local.sh
# Pulls WordPress database, assets, and optional plugins/themes/users from UAT into local Docker

set -e

# --- FLAG PARSING ---
INCLUDE_USERS=false
INCLUDE_PLUGINS=false
EXCLUDE_TABLES="--exclude_tables=wp_users,wp_usermeta"

for arg in "$@"; do
  case $arg in
    --include-users|-u)
      INCLUDE_USERS=true
      EXCLUDE_TABLES=""
      ;;
    --include-plugins|-p)
      INCLUDE_PLUGINS=true
      ;;
    --all|-a)
      INCLUDE_USERS=true
      INCLUDE_PLUGINS=true
      EXCLUDE_TABLES=""
      ;;
  esac
done

# --- CONFIGURATION ---
UAT_HOST="uat.vinttro.co.uk"
UAT_USER="webhook"
UAT_PATH="/var/www/wordpress"
UAT_URL="https://uat.vinttro.co.uk"

LOCAL_URL="http://localhost:8081"
SSH_KEY="$HOME/.ssh/id_ed25519"

WP_CONTAINER="wordpress_web"
DB_CONTAINER="vinttro_db"
DB_NAME="wordpress"

SSH_OPTS="-i $SSH_KEY -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=30"

echo "=================================================="
echo "🚀 Starting WordPress Sync from UAT to Local Docker"
[ "$INCLUDE_USERS" = true ] && echo "👥 User Sync: INCLUDED" || echo "🔒 User Sync: EXCLUDED (Pass -u or --include-users)"
[ "$INCLUDE_PLUGINS" = true ] && echo "🔌 Plugin & Theme Sync: INCLUDED" || echo "⚡ Plugin & Theme Sync: EXCLUDED (Pass -p or --include-plugins)"
echo "=================================================="

# --- 1. ENSURE WP-CLI IS INSTALLED IN DOCKER ---
echo "🔧 Checking WP-CLI inside $WP_CONTAINER..."
docker exec -u 0 -i $WP_CONTAINER bash -c "
  if [ ! -f /usr/local/bin/wp ]; then
    echo 'Downloading WP-CLI...'
    curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
  fi
"

# --- 2. SYNC PLUGINS & THEMES FIRST (IF REQUESTED) ---
if [ "$INCLUDE_PLUGINS" = true ]; then
  echo "🔌 Syncing plugins directory from UAT..."
  mkdir -p /tmp/wp-plugins-temp
  rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_HOST:$UAT_PATH/wp-content/plugins/ /tmp/wp-plugins-temp/

  echo "📦 Copying plugins into container..."
  docker exec -u 0 -i $WP_CONTAINER mkdir -p /var/www/html/wp-content/plugins
  docker cp /tmp/wp-plugins-temp/. $WP_CONTAINER:/var/www/html/wp-content/plugins/
  docker exec -u 0 -i $WP_CONTAINER chown -R www-data:www-data /var/www/html/wp-content/plugins
  rm -rf /tmp/wp-plugins-temp

  echo "🎨 Syncing themes directory from UAT..."
  mkdir -p /tmp/wp-themes-temp
  rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_HOST:$UAT_PATH/wp-content/themes/ /tmp/wp-themes-temp/

  echo "📦 Copying themes into container..."
  docker exec -u 0 -i $WP_CONTAINER mkdir -p /var/www/html/wp-content/themes
  docker cp /tmp/wp-themes-temp/. $WP_CONTAINER:/var/www/html/wp-content/themes/
  docker exec -u 0 -i $WP_CONTAINER chown -R www-data:www-data /var/www/html/wp-content/themes
  rm -rf /tmp/wp-themes-temp
fi

# --- 3. UAT DATABASE EXPORT & LOCAL IMPORT ---
echo "📥 Exporting database from UAT server..."
ssh $SSH_OPTS $UAT_USER@$UAT_HOST "wp db export --path=$UAT_PATH $EXCLUDE_TABLES /tmp/uat_wp_local_dump.sql"

echo "🚚 Transferring SQL dump to local machine..."
scp $SSH_OPTS $UAT_USER@$UAT_HOST:/tmp/uat_wp_local_dump.sql /tmp/uat_wp_local_dump.sql

echo "📂 Importing SQL dump into local MariaDB container..."
docker exec -i $DB_CONTAINER mysql -uroot -proot_password $DB_NAME < /tmp/uat_wp_local_dump.sql

# --- 4. SYNC UPLOADS MEDIA DIRECTORY ---
echo "🖼️ Syncing uploads directory from UAT..."
mkdir -p /tmp/wp-uploads-temp
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_HOST:$UAT_PATH/wp-content/uploads/ /tmp/wp-uploads-temp/

echo "📦 Copying uploads into container..."
docker exec -u 0 -i $WP_CONTAINER mkdir -p /var/www/html/wp-content/uploads
docker cp /tmp/wp-uploads-temp/. $WP_CONTAINER:/var/www/html/wp-content/uploads/
rm -rf /tmp/wp-uploads-temp

# --- 5. SEARCH & REPLACE URLS ---
echo "🔍 Running Search and Replace..."
docker exec -i -u www-data $WP_CONTAINER wp search-replace "https://uat.vinttro.co.uk" "$LOCAL_URL" --all-tables --path=/var/www/html
docker exec -i -u www-data $WP_CONTAINER wp search-replace "uat.vinttro.co.uk" "localhost:8081" --all-tables --path=/var/www/html
docker exec -i -u www-data $WP_CONTAINER wp search-replace ".uat@" "@" --all-tables --path=/var/www/html

echo "⚙️ Updating siteurl and home options..."
docker exec -i -u www-data $WP_CONTAINER wp option update siteurl "$LOCAL_URL" --path=/var/www/html
docker exec -i -u www-data $WP_CONTAINER wp option update home "$LOCAL_URL" --path=/var/www/html

# --- 6. DISABLE SSL ENFORCEMENT PLUGINS LOCALLY ---
echo "🔓 Disabling SSL redirect plugins locally..."
docker exec -i -u www-data $WP_CONTAINER wp plugin deactivate really-simple-ssl wp-force-ssl ssl-insecure-content-fixer --path=/var/www/html 2>/dev/null || true

# --- 7. ASSETS & ELEMENTOR REGENERATION ---
if docker exec -i -u www-data $WP_CONTAINER wp plugin is-active elementor --path=/var/www/html 2>/dev/null; then
    echo "🎨 Running Elementor URL Replace & Flushing CSS..."
    docker exec -i -u www-data $WP_CONTAINER wp elementor replace-urls "https://uat.vinttro.co.uk" "$LOCAL_URL" --path=/var/www/html
    docker exec -i -u www-data $WP_CONTAINER wp elementor flush-css --path=/var/www/html
fi

# --- 8. CACHE & PERMISSIONS ---
echo "🛠️ Flushing rewrite rules and Redis cache..."
docker exec -i -u www-data $WP_CONTAINER wp rewrite flush --path=/var/www/html
docker exec -i vinttro_redis redis-cli -n 0 flushdb > /dev/null 2>&1

echo "🔒 Setting permissions..."
docker exec -u 0 -i $WP_CONTAINER chown -R www-data:www-data /var/www/html/wp-content

# --- 8.1 Force password protection to 0
docker exec -i -u www-data $WP_CONTAINER wp option update password_protected_status 0 --path=/var/www/html

# --- 9. CLEANUP REMOTE TEMP FILES ---
rm -f /tmp/uat_wp_local_dump.sql
ssh $SSH_OPTS $UAT_USER@$UAT_HOST "rm -f /tmp/uat_wp_local_dump.sql"

echo "=================================================="
echo "✅ Sync complete! Access at: $LOCAL_URL"
echo "=================================================="