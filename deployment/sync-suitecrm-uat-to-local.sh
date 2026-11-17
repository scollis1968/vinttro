#!/bin/bash
# test
# sync-suitecrm-uat-to-local.sh
# Pulls full SuiteCRM 8 codebase and database (via Cloud SQL proxy on port 3330) from UAT into local Docker

set -e

# --- CONFIGURATION ---
UAT_HOST="services.uat.vinttro.co.uk"
UAT_USER="webhook"
UAT_PATH="/var/www/suitecrm"

LOCAL_SUITECRM_CONTAINER="suitecrm_web"
LOCAL_DB_CONTAINER="vinttro_db"
LOCAL_DB_NAME="suitecrm"
LOCAL_DB_USER="root"
LOCAL_DB_PASS="root_password"

SSH_KEY="$HOME/.ssh/id_ed25519"
SSH_OPTS="-i $SSH_KEY -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=30"

echo "=================================================="
echo "🚀 Starting Full SuiteCRM 8 Sync from UAT"
echo "=================================================="

# --- 1. SYNC SUITECRM 8 CODEBASE ---
echo "📦 Syncing SuiteCRM 8 codebase from UAT..."
mkdir -p /tmp/suitecrm-app-temp

rsync -avz --exclude='cache/' --exclude='logs/' --exclude='upload/' \
  -e "ssh $SSH_OPTS" \
  $UAT_USER@$UAT_HOST:$UAT_PATH/ /tmp/suitecrm-app-temp/

echo "📦 Copying codebase into local SuiteCRM container..."
docker cp /tmp/suitecrm-app-temp/. $LOCAL_SUITECRM_CONTAINER:/var/www/html/
docker exec -u 0 -i $LOCAL_SUITECRM_CONTAINER chown -R www-data:www-data /var/www/html
rm -rf /tmp/suitecrm-app-temp

# --- 2. CONFIGURE APACHE DOCUMENTROOT ---
echo "🔧 Ensuring Apache DocumentRoot is set to /var/www/html/public..."
docker exec -u 0 -i $LOCAL_SUITECRM_CONTAINER bash -c "
cat << 'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
a2enmod rewrite > /dev/null 2>&1 || true
"

# --- 2.1 CONFIGURE SUITECRM TO USE LOCAL DOCKER MARIADB ---
echo "⚙️ Updating SuiteCRM database configs for local Docker environment..."
docker exec -u 0 -i $LOCAL_SUITECRM_CONTAINER bash -c "
# Update Legacy SuiteCRM config
if [ -f /var/www/html/public/legacy/config.php ]; then
  sed -i \"s/'db_host_name' => .*/'db_host_name' => 'db',/\" /var/www/html/public/legacy/config.php
  sed -i \"s/'db_port' => .*/'db_port' => '3306',/\" /var/www/html/public/legacy/config.php
  sed -i \"s/'db_user_name' => .*/'db_user_name' => '$LOCAL_DB_USER',/\" /var/www/html/public/legacy/config.php
  sed -i \"s/'db_password' => .*/'db_password' => '$LOCAL_DB_PASS',/\" /var/www/html/public/legacy/config.php
  sed -i \"s/'db_name' => .*/'db_name' => '$LOCAL_DB_NAME',/\" /var/www/html/public/legacy/config.php
  sed -i \"s|'site_url' => .*|'site_url' => 'http://localhost:8080',|\" /var/www/html/public/legacy/config.php
fi

# Update SuiteCRM 8 Symfony environment database URL
if [ -f /var/www/html/.env.local ]; then
  sed -i 's|DATABASE_URL=.*|DATABASE_URL=\"mysql://$LOCAL_DB_USER:$LOCAL_DB_PASS@db:3306/$LOCAL_DB_NAME?serverVersion=mariadb-10.6.0\"|' /var/www/html/.env.local
fi
"

# --- 3. EXPORT CLOUD SQL DATABASE FROM UAT (PORT 3330) ---
echo "📥 Exporting database from UAT Cloud SQL proxy (Port 3330)..."

DUMP_SCRIPT=$(cat << 'EOF'
<?php
define('sugarEntry', true);
include '/var/www/suitecrm/public/legacy/config.php';
$db = $sugar_config['dbconfig'];

$host = !empty($db['db_host_name']) ? $db['db_host_name'] : '127.0.0.1';
$port = !empty($db['db_port']) ? $db['db_port'] : '3330';
$user = !empty($db['db_user_name']) ? $db['db_user_name'] : 'suitecrm_db_user';
$pass = isset($db['db_password']) ? $db['db_password'] : 'gsjdjsHJGJH_1_Ghx';
$name = !empty($db['db_name']) ? $db['db_name'] : 'uat_suitecrm';

$cmd = sprintf('MYSQL_PWD=%s mysqldump -h %s -P %s -u %s --single-transaction --quick %s > /tmp/uat_suitecrm_local_dump.sql',
  escapeshellarg($pass),
  escapeshellarg($host),
  escapeshellarg($port),
  escapeshellarg($user),
  escapeshellarg($name)
);
system($cmd, $ret);
exit($ret);
EOF
)

B64_DUMP_SCRIPT=$(echo "$DUMP_SCRIPT" | base64 | tr -d '\r\n')
ssh $SSH_OPTS $UAT_USER@$UAT_HOST "echo '$B64_DUMP_SCRIPT' | base64 -d | php"

echo "🚚 Transferring SQL dump to local machine..."
scp $SSH_OPTS $UAT_USER@$UAT_HOST:/tmp/uat_suitecrm_local_dump.sql /tmp/uat_suitecrm_local_dump.sql

echo "📂 Importing SQL dump into local DB container..."
docker exec -i $LOCAL_DB_CONTAINER mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS -e "DROP DATABASE IF EXISTS $LOCAL_DB_NAME; CREATE DATABASE $LOCAL_DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker exec -i $LOCAL_DB_CONTAINER mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME < /tmp/uat_suitecrm_local_dump.sql

# --- 4. RESTART CONTAINER & QUICK REPAIR ---
echo "🔄 Restarting SuiteCRM web container..."
docker restart $LOCAL_SUITECRM_CONTAINER

echo "⚙️ Running SuiteCRM 8 Quick Repair & Rebuild..."
REPAIR_SCRIPT=$(cat << 'EOF'
<?php
chdir('/var/www/html/public/legacy');
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');
$rand = new RepairAndClear();
$rand->repairAndClearAll(array('clearAll'), array('All Modules'), true, false);
EOF
)

echo "$REPAIR_SCRIPT" | docker exec -i $LOCAL_SUITECRM_CONTAINER php || true

# --- 5. CLEANUP REMOTE & LOCAL TEMP FILES ---
echo "🧹 Cleaning up temporary files..."
rm -f /tmp/uat_suitecrm_local_dump.sql
ssh $SSH_OPTS $UAT_USER@$UAT_HOST "rm -f /tmp/uat_suitecrm_local_dump.sql"

echo "=================================================="
echo "✅ Full SuiteCRM 8 Sync Complete! Access at: http://localhost:8080"
echo "=================================================="