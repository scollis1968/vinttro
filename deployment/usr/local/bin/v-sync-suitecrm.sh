#!/bin/bash

# --- CONFIGURATION ---
UAT_IP="10.154.0.3"
UAT_USER="webhook"
UAT_PATH="/var/www/suitecrm"
PROD_PATH="/var/www/suitecrm"
UAT_URL="https://uat.vinttro.co.uk"
PROD_URL="https://www.vinttro.co.uk"
LOG_FILE="/var/log/v-sync-suitecrm.log"
KEY="/var/www/.ssh/id_ed25519"

# SSH options telling it where the key is and to bypass interactive prompts
SSH_OPTS="-i $KEY -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=30"

# Automatically duplicate all stdout and stderr into the log file safely
exec > >(tee -a "$LOG_FILE") 2>&1

echo "================================================================="
echo "🚀 Starting Secure SuiteCRM Sync: $(date '+%Y-%m-%d %H:%M:%S')"
echo "================================================================="

# -------------------------------------------------------------------------
# 1. Sync Application Files
# -------------------------------------------------------------------------
echo "-> Syncing Extensions & Custom Folders..."
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/extensions/ $PROD_PATH/extensions/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/public/dist/extensions/ $PROD_PATH/public/dist/extensions/
rsync -avz -e "ssh $SSH_OPTS" $UAT_USER@$UAT_IP:$UAT_PATH/public/legacy/custom/ $PROD_PATH/public/legacy/custom/

echo "-> Syncing compiled visp module core engines..."
rsync -avz -e "ssh $SSH_OPTS" \
    --include='visp_*/' --include='visp_**' --exclude='*' \
    $UAT_USER@$UAT_IP:$UAT_PATH/public/legacy/modules/ $PROD_PATH/public/legacy/modules/

echo "-> Fixing system file permissions..."
chown -R www-data:www-data $PROD_PATH/extensions/
chown -R www-data:www-data $PROD_PATH/public/dist/extensions/
chown -R www-data:www-data $PROD_PATH/public/legacy/custom/
chown -R www-data:www-data $PROD_PATH/public/legacy/modules/

# -------------------------------------------------------------------------
# 2. Automated Studio Field Database Sync
# -------------------------------------------------------------------------
echo "-> Fetching Studio custom field records from UAT database..."
ssh $SSH_OPTS $UAT_USER@$UAT_IP /usr/bin/php << 'EOF' > /tmp/uat_fields_meta_data.json
<?php
// Clear and mock environment context on UAT fetcher
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
if (file_exists("/var/www/suitecrm/public/legacy/include/entryPoint.php")) {
    chdir("/var/www/suitecrm/public/legacy");
    require_once("include/entryPoint.php");
    $db = DBManagerFactory::getInstance();
    $res = $db->query("SELECT * FROM fields_meta_data WHERE deleted = 0");
    $rows = array();
    while($row = $db->fetchByAssoc($res)) {
        $rows[] = $row;
    }
    echo json_encode($rows);
}
EOF

echo "-> Merging field metadata schemas into Production database..."
cd $PROD_PATH/public/legacy
/usr/bin/env -i /usr/bin/php << 'EOF'
<?php
// Clear and mock environment context on Prod merger
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
require_once("include/entryPoint.php");
$db = DBManagerFactory::getInstance();

$jsonFile = "/tmp/uat_fields_meta_data.json";
if (file_exists($jsonFile)) {
    $rows = json_decode(file_get_contents($jsonFile), true);
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $id = $db->quote($row['id']);
            $db->query("DELETE FROM fields_meta_data WHERE id = '$id'");
            
            $columns = array();
            $values = array();
            foreach ($row as $col => $val) {
                $columns[] = $col;
                if ($val === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $db->quote($val) . "'";
                }
            }
            $sql = "INSERT INTO fields_meta_data (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ")";
            $db->query($sql);
        }
        echo "   -> Successfully synced " . count($rows) . " custom studio field definitions.\n";
    }
}
EOF

# -------------------------------------------------------------------------
# 3. Framework Compilation & Automated Repair Updates
# -------------------------------------------------------------------------
# Ensure target path location context is absolutely aligned before running PHP
cd $PROD_PATH/public/legacy

echo "-> Cleaning Stale Caches and Rebuilding SuiteCRM Extensions (Dynamic Layout)..."
/usr/bin/env -i /usr/bin/php << 'EOF'
<?php
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
require_once("include/entryPoint.php");
require_once("ModuleInstall/ModuleInstaller.php");

global $current_user;
$current_user = new User();
$current_user->is_admin = 1;
$current_user->id = "1";

# 1. Map target core system modules
$target_modules = array("Contacts", "Accounts", "Leads", "Opportunities", "Cases", "Tasks", "Calls", "Meetings");

# 2. Automatically discover all compiled visp folders inside the modules directory
$visp_directories = glob("modules/visp_*", GLOB_ONLYDIR);

if (is_array($visp_directories)) {
    foreach ($visp_directories as $path) {
        $target_modules[] = basename($path);
    }
}

# 🔥 REFACTORED: Dynamically nuke legacy caches using the exact module array maps
echo "   -> Removing stale legacy caches for target modules...\n";
foreach ($target_modules as $module) {
    $cache_path = "cache/modules/" . $module;
    if (file_exists($cache_path)) {
        // Run clean directory deletions through low-impact shell operations natively
        shell_exec("rm -rf " . escapeshellarg($cache_path));
    }
}

# Drop legacy class maps to force path indexing transformations
if (file_exists("cache/class_map.php")) {
    @unlink("cache/class_map.php");
}

# 3. Feed the dynamically generated array to the installer engine
$mi = new ModuleInstaller();
$mi->modules = $target_modules;

echo "   -> Discovered modules for extension rebuild: " . implode(", ", $target_modules) . "\n";
$mi->rebuild_extensions();
EOF

echo "-> Executing SuiteCRM Database Repair (Forces ALTER TABLE on core schema changes)..."
/usr/bin/env -i /usr/bin/php << 'EOF'
<?php
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
require_once("include/entryPoint.php");

global $current_user;
$current_user = new User();
$current_user->is_admin = 1;
$current_user->id = "1";

require_once("modules/Administration/QuickRepairAndRebuild.php");
global $moduleList; 
$repair = new RepairAndClear(); 
$repair->repairAndClearAll(array("clearAll"), $moduleList, true, false);
EOF

echo "-> Syncing Core Backend Layout Assets to Frontend..."
/usr/bin/env -i /usr/bin/php "$PROD_PATH/bin/console" scrm:copy-legacy-assets

# =========================================================================
# 🚀 PATCH INJECTED: Fix official SuiteCRM 8.10.1 Webpack tuple mismatch on Prod
# This safely fixes the factory files on Production right after compilation
# =========================================================================
echo "-> Aligning compiled frontend core asset version requirements (18,2,8 -> 18,2,14)..."
find "$PROD_PATH/public/dist/" -type f -name "*.js" -exec sed -i 's/18,2,8/18,2,14/g' {} +
# =========================================================================

echo "-> Flushing SuiteCRM 8 Production Container Cache..."
/usr/bin/env -i /usr/bin/php "$PROD_PATH/bin/console" cache:clear

# Clean up local temporary file
rm -f /tmp/uat_fields_meta_data.json

echo "================================================================="
echo "✅ SuiteCRM Promotion & Core Customizations Completed Successfully!"
echo "================================================================="