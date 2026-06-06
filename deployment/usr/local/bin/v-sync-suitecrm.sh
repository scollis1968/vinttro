#!/bin/bash# --- CONFIGURATION ---

UAT_IP="10.154.0.3"

UAT_USER="webhook"

UAT_PATH="/var/www/suitecrm"
PROD_PATH="/var/www/suitecrm"

UAT_URL="https://uat.vinttro.co.uk"

PROD_URL="https://www.vinttro.co.uk"

LOG_FILE="/var/log/v-sync-suitecrm.log"

# Use the key we just moved

KEY="/var/www/.ssh/id_ed25519"
# --- CONFIGURATION (Keep your existing SSH/IP variables here) ---

echo "🚀 Starting Secure SuiteCRM Sync..."

# 1. Sync Files
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/extensions/ $PROD_PATH/extensions/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/public/dist/extensions/ $PROD_PATH/public/dist/extensions/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/public/legacy/custom/ $PROD_PATH/public/legacy/custom/

# 2. Permissions
chown -R www-data:www-data $PROD_PATH/extensions/
chown -R www-data:www-data $PROD_PATH/public/dist/extensions/
chown -R www-data:www-data $PROD_PATH/public/legacy/custom/

# 3. Framework & DB Rebuilds
cd $PROD_PATH
echo "   -> Rebuilding SuiteCRM Extensions..."
sudo -u www-data php -r '
    define("sugarEntry", true);
    $_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
    if (isset($_SERVER)) { $_SERVER["argv"] = array(); }
    require_once("/var/www/suitecrm/public/legacy/include/entryPoint.php");
    require_once("/var/www/suitecrm/public/legacy/ModuleInstall/ModuleInstaller.php");
    $mi = new ModuleInstaller();
    $mi->modules = array("Contacts"); // Add other modules here if "visp" targets more than Contacts
    $mi->rebuild_extensions();
'
echo "   -> Syncing Core Backend Layout Assets to Frontend..."
sudo -u www-data php bin/console scrm:copy-legacy-assets

# E. CRITICAL: Execute Database Repair to align DB with new "visp" module schema changes
echo "   -> Executing SuiteCRM Database Repair..."
sudo -u www-data php -r '
    define("sugarEntry", true);
    require_once("/var/www/suitecrm/public/legacy/include/entryPoint.php");
    require_once("/var/www/suitecrm/public/legacy/Administration/QuickRepairAndRebuild.php");
    $repair = new QuickRepairAndRebuild();
    // This executes vardef repairs and executes SQL changes automatically
    $repair->repairAndClearAll(array("clearAll"), array("All Modules"), true, false);
'

echo "   -> Flushing SuiteCRM 8 Production Container Cache..."
sudo -u www-data php bin/console cache:clear


echo "✅ SuiteCRM Promotion Complete!"