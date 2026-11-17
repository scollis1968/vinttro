#!/bin/bash

# --- Configuration ---
REPO_URL="https://github.com/scollis1968/vinttro.git"
STAGING_DIR="/tmp/vinttro-repo"
LIVE_DIR="/var/www/wordpress"
LOG_FILE="/var/log/vinttro-deploy.log"
LOCK_FILE="/var/lock/vinttro-deploy.lock"

# Optional local server override file (e.g., echo 'BRANCH="main"' > /etc/vinttro-deploy.conf)
[ -f /etc/vinttro-deploy.conf ] && source /etc/vinttro-deploy.conf

# Allow CLI argument to override config, defaulting to 'uat'
BRANCH="${1:-${BRANCH:-uat}}"

# --- Logging Function ---
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [$BRANCH] - $1" >> "$LOG_FILE"
}

# --- Force Root Elevation via Sudoers rule ---
if [ "$EUID" -ne 0 ]; then
    exec sudo "$0" "$@"
fi

# --- Prevent Overlapping Deployments ---
exec 200>"$LOCK_FILE"
if ! flock -n 200; then
    log "WARNING: Another deployment is already in progress. Exiting."
    exit 1
fi

log "================================================================="
log "🚀 Deployment triggered for Vinttro (Branch: $BRANCH)"
log "================================================================="

# --- 1. Clone or Pull the Repository ---
if [ ! -d "$STAGING_DIR/.git" ]; then
    log "Cloning repository (branch: $BRANCH)..."
    git clone -b "$BRANCH" "$REPO_URL" "$STAGING_DIR" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Git clone failed."
        exit 1
    fi
else
    log "Pulling latest changes from origin/$BRANCH..."
    cd "$STAGING_DIR" || exit 1
    git fetch origin >> "$LOG_FILE" 2>&1
    git checkout "$BRANCH" >> "$LOG_FILE" 2>&1
    git reset --hard "origin/$BRANCH" >> "$LOG_FILE" 2>&1
    git clean -fd >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Git pull/reset failed."
        exit 1
    fi
fi

# --- 2. Pre-Deployment DB Snapshot (Production Safety Net) ---
if [ "$BRANCH" = "main" ] || [ "$BRANCH" = "prod" ]; then
    BACKUP_DIR="/var/backups/suitecrm"
    mkdir -p "$BACKUP_DIR"
    log "Creating pre-deployment SuiteCRM database snapshot..."
    /usr/bin/php << 'EOF' >> "$LOG_FILE" 2>&1
<?php
$envFile = "/var/www/suitecrm/.env.local";
$dbUrl = null;

if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (preg_match('/^DATABASE_URL=(.+)$/', trim($line), $m)) {
            $dbUrl = trim($m[1], "\"'");
            break;
        }
    }
}

if ($dbUrl) {
    $parts = parse_url($dbUrl);
    $host = $parts['host'] ?? '127.0.0.1';
    $port = $parts['port'] ?? 3306;
    $user = rawurldecode($parts['user'] ?? '');
    $pass = rawurldecode($parts['pass'] ?? '');
    $name = ltrim($parts['path'] ?? '', '/');
} else {
    define("sugarEntry", true);
    chdir("/var/www/suitecrm/public/legacy");
    require_once("include/entryPoint.php");
    $opts = DBManagerFactory::getInstance()->connectOptions;
    $host = $opts['db_host_name'];
    $port = 3306;
    $user = $opts['db_user_name'];
    $pass = $opts['db_password'];
    $name = $opts['db_name'];
}

$file = "/var/backups/suitecrm/pre_deploy_" . date("Ymd_His") . ".sql.gz";
putenv("MYSQL_PWD=" . $pass);

$cmd = sprintf(
    "mysqldump --single-transaction -h %s -P %d -u %s %s | gzip > %s",
    escapeshellarg($host),
    (int)$port,
    escapeshellarg($user),
    escapeshellarg($name),
    escapeshellarg($file)
);
system($cmd, $ret);
putenv("MYSQL_PWD");

if ($ret === 0 && file_exists($file) && filesize($file) > 1024) {
    echo "   -> Backup saved: $file (" . round(filesize($file) / 1024, 1) . " KB)\n";
} else {
    @unlink($file);
    echo "   -> WARNING: Database backup failed (exit code $ret).\n";
}
EOF
    # Keep only the 7 most recent pre-deploy backups
    ls -t "$BACKUP_DIR"/pre_deploy_*.sql.gz 2>/dev/null | tail -n +8 | xargs -r rm -f
fi


# --- 3. Extract and Deploy Custom WordPress Files ---
log "Deploying Vinttro Plugin..."
rsync -a "$STAGING_DIR/wp-content/plugins/vinttro2.0/" "$LIVE_DIR/wp-content/plugins/vinttro2.0/"
if [ $? -ne 0 ]; then
    log "ERROR: Plugin rsync failed."
    exit 1
fi
chown -R www-data:www-data "$LIVE_DIR/wp-content/plugins/vinttro2.0/"

log "Deploying Vinttro Theme..."
THEME_NAME="vinttro_child_theme"
SOURCE_DIR="$STAGING_DIR/wp-content/themes/$THEME_NAME"
DEST_DIR="$LIVE_DIR/wp-content/themes/$THEME_NAME"

if [ -d "$SOURCE_DIR" ]; then
    rsync -a "$SOURCE_DIR/" "$DEST_DIR/" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying VINTTRO theme failed."
        exit 1
    fi
    chown -R www-data:www-data "$DEST_DIR/"
else
    log "ERROR: Source theme directory $SOURCE_DIR not found!"
    exit 1
fi

log "Deploying SuiteCRM/VINTTRO customisation scripts..."
SOURCE_DIR="$STAGING_DIR/suitecrm/vinttro2.0/"
DESTINATION_DIR="/var/www/suitecrm/vinttro2.0/"
if [ -d "$SOURCE_DIR" ]; then
    rsync -a "$SOURCE_DIR" "$DESTINATION_DIR"
    chown -R www-data:www-data "$DESTINATION_DIR"
fi

# --- 4. Deploy vinttro-api Microservice ---
SOURCE_DIR="$STAGING_DIR/vinttro-api/"
DESTINATION_DIR="/var/www/vinttro-api/"
APP_USER="vinttro"

if [ -d "$SOURCE_DIR" ]; then
    log "Executing vinttro-api asset synchronization..."
    rsync -a --delete --exclude 'node_modules' --exclude '.env' "$SOURCE_DIR" "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying $DESTINATION_DIR failed during rsync."
        exit 1
    fi
    chown -R "$APP_USER":"$APP_USER" "$DESTINATION_DIR"
else
    log "ERROR: Source directory $SOURCE_DIR not found in repository!"
    exit 1
fi

cd "$DESTINATION_DIR" || exit 1
log "Installing/updating Node.js production dependencies as $APP_USER..."
sudo -u "$APP_USER" npm install --omit=dev >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: npm install failed in $DESTINATION_DIR"
    exit 1
fi

log "Restarting vinttro-api microservice via systemctl..."
systemctl restart vinttro-api >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: systemctl restart vinttro-api failed!"
    exit 1
fi

sleep 2

# --- 5. Deploy SuiteCRM Core & UI Assets ---
log "Deploying SuiteCRM frontend extensions..."
if [ -d "$STAGING_DIR/suitecrm/extensions/" ]; then
    rsync -a "$STAGING_DIR/suitecrm/extensions/" "/var/www/suitecrm/extensions/" >> "$LOG_FILE" 2>&1
fi

SOURCE_UI="$STAGING_DIR/suitecrm/public/dist/extensions/vinttro-custom-ui/"
DEST_UI="/var/www/suitecrm/public/dist/extensions/vinttro-custom-ui/"
if [ -d "$SOURCE_UI" ]; then
    mkdir -p "$DEST_UI" >> "$LOG_FILE" 2>&1
    rsync -a "$SOURCE_UI" "$DEST_UI" >> "$LOG_FILE" 2>&1
fi

log "Deploying SuiteCRM public/legacy codebase..."
cd "$STAGING_DIR" || exit 1
rsync -aR "suitecrm/public/legacy" "/var/www/" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: Relative deploy of suitecrm/public/legacy failed."
    exit 1
fi

# Set ownership before running CLI PHP / Symfony tools
chown -R www-data:www-data /var/www/suitecrm/public/ /var/www/suitecrm/extensions/

# --- 6. Studio Metadata Sync & Automated SuiteCRM Rebuilds ---
log "Checking for Studio custom field metadata updates from Git..."
cd /var/www/suitecrm/public/legacy/ || exit 1

sudo -u www-data /usr/bin/env -i /usr/bin/php << 'EOF' >> "$LOG_FILE" 2>&1
<?php
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
require_once("include/entryPoint.php");
$db = DBManagerFactory::getInstance();

$jsonFile = "/tmp/vinttro-repo/suitecrm/fields_meta_data.json";
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
                $values[] = ($val === null) ? "NULL" : "'" . $db->quote($val) . "'";
            }
            $sql = "INSERT INTO fields_meta_data (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ")";
            $db->query($sql);
        }
        echo "   -> Synced " . count($rows) . " custom studio field definitions from Git.\n";
    }
}
EOF

log "Cleaning module caches and rebuilding SuiteCRM extensions..."
sudo -u www-data /usr/bin/env -i /usr/bin/php << 'EOF' >> "$LOG_FILE" 2>&1
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

$target_modules = array("Contacts", "Accounts", "Leads", "Opportunities", "Cases", "Tasks", "Calls", "Meetings");
foreach (glob("modules/visp_*", GLOB_ONLYDIR) as $dir) {
    $target_modules[] = basename($dir);
}
$target_modules = array_unique($target_modules);

foreach ($target_modules as $mod) {
    $cache_path = "cache/modules/" . $mod;
    if (file_exists($cache_path)) {
        shell_exec("rm -rf " . escapeshellarg($cache_path));
    }
}
if (file_exists("cache/class_map.php")) {
    @unlink("cache/class_map.php");
}

echo "--- REBUILDING EXTENSIONS FOR: " . implode(", ", $target_modules) . "\n";
$mi = new ModuleInstaller();
$mi->modules = $target_modules;
$mi->rebuild_extensions();
EOF

log "Compiling Master Logic Hooks Direct From Extensions Source..."
sudo -u www-data /usr/bin/env -i /usr/bin/php << 'EOF' >> "$LOG_FILE" 2>&1
<?php
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
require_once("include/entryPoint.php");

$modules = array("Contacts");
foreach (glob("modules/visp_*", GLOB_ONLYDIR) as $dir) {
    $modules[] = basename($dir);
}
$modules = array_unique($modules);

foreach ($modules as $mod) {
    $master = "custom/modules/" . $mod . "/logic_hooks.php";
    $ext_dir = "custom/Extension/modules/" . $mod . "/Ext/LogicHooks";
    $compiled_ext = "custom/modules/" . $mod . "/Ext/LogicHooks/logichooks.ext.php";
    
    $hook_array = array(); $hook_version = 1;
    if (is_dir($ext_dir)) {
        foreach (glob($ext_dir . "/*.php") as $ext_file) {
            include($ext_file);
        }
    }
    if (file_exists($compiled_ext)) { 
        include($compiled_ext); 
    }
    
    if (!empty($hook_array)) {
        if (!is_dir(dirname($master))) {
            mkdir(dirname($master), 0775, true);
        }
        $content = "<?php\n\$hook_version = 1;\n\$hook_array = " . var_export($hook_array, true) . ";\n";
        file_put_contents($master, $content);
    }
}
EOF

log "Executing Automated QRR (Creates missing DB tables and schema alterations)..."
sudo -u www-data /usr/bin/env -i /usr/bin/php << 'EOF' >> "$LOG_FILE" 2>&1
<?php
$_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
$_SERVER = array('PHP_SELF' => 'cron.php', 'SCRIPT_NAME' => 'cron.php', 'argv' => array('cron.php'), 'argc' => 1, 'REQUEST_METHOD' => 'CLI');

define("sugarEntry", true);
require_once("include/entryPoint.php");
require_once("modules/Administration/QuickRepairAndRebuild.php");

global $current_user, $moduleList;
$current_user = new User();
$current_user->is_admin = 1;
$current_user->id = "1";

$repair = new RepairAndClear();
$repair->repairAndClearAll(array("clearAll"), $moduleList, true, false);
EOF

log "Syncing Core Backend Layout Assets to Frontend..."
cd /var/www/suitecrm || exit 1
sudo -u www-data php bin/console scrm:copy-legacy-assets >> "$LOG_FILE" 2>&1

log "Executing dynamic compilation alignment on frontend distribution assets..."
find /var/www/suitecrm/public/dist/ -type f -name "*.js" -exec sed -i 's/18,2,8/18,2,14/g' {} +
find /var/www/suitecrm/public/dist/ -type f -name "*.js" -exec sed -i 's/3,5,0/3,7,2/g' {} +
find /var/www/suitecrm/public/dist/ -type f -name "*.js" -exec sed -i 's/"^auto"/"^8.10.1"/g' {} +
find /var/www/suitecrm/public/dist/ -type f -name "*.js" -exec sed -i "s/'^auto'/'^8.10.1'/g" {} +

log "Optimizing file ownership and permissions schema (Batched)..."
chown -R www-data:www-data /var/www/suitecrm/public/ /var/www/suitecrm/extensions/
find /var/www/suitecrm/public/ /var/www/suitecrm/extensions/ -type d -exec chmod 775 {} +
find /var/www/suitecrm/public/ /var/www/suitecrm/extensions/ -type f -exec chmod 664 {} +

log "Flushing SuiteCRM 8 Production Container Cache..."
sudo -u www-data php bin/console cache:clear --env=prod >> "$LOG_FILE" 2>&1

log "✅ Deployment successful for branch: $BRANCH"
exit 0