#!/bin/bash

# --- Configuration ---
REPO_URL="https://github.com/scollis1968/vinttro.git"
STAGING_DIR="/tmp/vinttro-repo"
LIVE_DIR="/var/www/wordpress"
LOG_FILE="/var/log/vinttro-deploy.log"
TARGET_BRANCH="refs/heads/uat" # <-- SET YOUR REQUIRED BRANCH HERE
BRANCH="uat"
# ----------------------------------------------------------------
# tip :- run the following command to execute this script and see the logs in real-time: 
#     journalctl -u webhook -f
# ----------------------------------------------------------------

# --- Logging Function ---
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log "--- Deployment triggered for Vinttro  - /etc/webhook/deploy-vinttro.sh ---"

# --- Force Root Elevation via Sudoers rule ---
if [ "$EUID" -ne 0 ]; then
    log "Script running as restricted user. Elevating execution to root..."
    exec sudo "$0" "$@"
fi

# --- 1. Clone or Pull the Repository ---
if [ ! -d "$STAGING_DIR/.git" ]; then
    log "Cloning repository...."
    git clone -b "$BRANCH" "$REPO_URL" "$STAGING_DIR"
    if [ $? -ne 0 ]; then
        log "ERROR: Git clone failed."
        exit 1
    fi
else
    log "Pulling latest changes..."
    cd "$STAGING_DIR" || exit 1
    git fetch origin
    git reset --hard origin/$BRANCH
    if [ $? -ne 0 ]; then
        log "ERROR: Git pull/reset failed."
        exit 1
    fi
fi

# --- 2. Extract and Deploy Custom WordPress Files (rsync) ---
log "Deploying Vinttro Plugin..."
rsync -a "$STAGING_DIR/wp-content/plugins/vinttro2.0/" "$LIVE_DIR/wp-content/plugins/vinttro2.0/"
if [ $? -ne 0 ]; then
    log "ERROR: Plugin rsync failed."
    exit 1
fi

log "Deploying Vinttro Theme..."
THEME_NAME="vinttro_child_theme"
SOURCE_DIR="$STAGING_DIR/wp-content/themes/$THEME_NAME"
DEST_DIR="/var/www/wordpress/wp-content/themes/$THEME_NAME"

if [ -d "$SOURCE_DIR" ]; then
    rsync -av "$SOURCE_DIR/" "$DEST_DIR/" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying VINTTRO theme failed."
        exit 1
    fi    
    chown -R www-data:www-data "$DEST_DIR/"
else
    log "ERROR: Source theme directory $SOURCE_DIR not found!"
    exit 1
fi


log "Deploying SuiteCrm/VINTTRO customisation ..."
SOURCE_DIR="/tmp/vinttro-repo/suitecrm/vinttro2.0/"
DESTINATION_DIR="/var/www/suitecrm/vinttro2.0/"
rsync -a "$SOURCE_DIR" "$DESTINATION_DIR"
if [ $? -ne 0 ]; then
    log "ERROR: Deploying SuiteCrm/VINTTRO customisation failed."
    exit 1
fi

log "Setting permissions on /var/www/suitecrm/vinttro2.0"
chown -R www-data:www-data /var/www/suitecrm/vinttro2.0


##-------------------------------------------------------------------------
# VINTTRO call-controller 
SOURCE_DIR="/tmp/vinttro-repo/call-controller/"
DESTINATION_DIR="/var/www/call-controller/"

# Hardcode the best-practice dedicated application runner user
APP_USER="vinttro" 

if [ -d "$SOURCE_DIR" ]; then
    log "Executing call-controller asset synchronization..."
    
    # Run rsync as the APP_USER so files are instantly born with the right ownership
    sudo -u "$APP_USER" rsync -a --delete "$SOURCE_DIR" "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying $DESTINATION_DIR failed during rsync."
        exit 1
    fi
else
    log "ERROR: Source directory $SOURCE_DIR not found in repository!"
    exit 1
fi

# Move into the app directory
cd "$DESTINATION_DIR"

log "Installing/updating Node.js production dependencies as $APP_USER..."
sudo -u "$APP_USER" npm install --omit=dev >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: npm install failed in $DESTINATION_DIR"
    exit 1
fi

log "Restarting call-controller process via PM2 as $APP_USER..."
# This ensures PM2 environments don't get mixed up between users
sudo -u "$APP_USER" pm2 restart "call-controller" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "WARNING: PM2 restart failed. Attempting initial start..."
    sudo -u "$APP_USER" pm2 start server.js --name "call-controller" --max-memory-restart 100M >> "$LOG_FILE" 2>&1
fi

log "Running post-deployment smoke tests..."
sudo -u "$APP_USER" npm test >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "🚨 ERROR: Post-deployment smoke tests FAILED!"
    exit 1
fi

log "🎉 Call-controller deployment and automated testing successful."


#
##-------------------------------------------------------------------------
# VINTTRO  SuiteCRM custom UI
SOURCE_DIR="/tmp/vinttro-repo/suitecrm/public/dist/extensions/vinttro-custom-ui/"
DESTINATION_DIR="/var/www/suitecrm/public/dist/extensions/vinttro-custom-ui/"

if [ -d "$SOURCE_DIR" ]; then
    log "Forcing absolute destination directory tree generation..."
    mkdir -p "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1
    chown -R www-data:www-data "/var/www/suitecrm/public/dist/" >> "$LOG_FILE" 2>&1

    log "Executing custom UI asset synchronization..."
    rsync -a "$SOURCE_DIR" "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying $DESTINATION_DIR failed during rsync."
        exit 1
    fi
else
    log "ERROR: Source UI directory $SOURCE_DIR not found in repository!"
    exit 1
fi

log "Setting permissions on $DESTINATION_DIR"
chown -R www-data:www-data "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1

##-------------------------------------------------------------------------
# VINTTRO  SuiteCRM  public/legacy (Using Relative Overlay).
cd /tmp/vinttro-repo || exit 1
RELATIVE_SOURCE="suitecrm/public/legacy"
TARGET_ROOT="/var/www"

log "Deploying SuiteCRM custom overlay..."
rsync -aR "$RELATIVE_SOURCE" "$TARGET_ROOT/" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: Relative deploy of $RELATIVE_SOURCE failed."
    exit 1
fi

log "Setting permissions on $TARGET_ROOT/$RELATIVE_SOURCE"
chown -R www-data:www-data "$TARGET_ROOT/$RELATIVE_SOURCE" >> "$LOG_FILE" 2>&1


##-------------------------------------------------------------------------
# --- 3. Automated Post-Deployment Automation & Framework Rebuilds ---
# Note: Root can safely run "sudo -u www-data" without ever requiring a password

log "Fixing front-end assets ownership paths..."
chown -R www-data:www-data /var/www/suitecrm/public/extensions >> "$LOG_FILE" 2>&1

log "Executing dynamic automated SuiteCRM Extensions Rebuild..."
sudo -u www-data php -r '
    define("sugarEntry", true);
    $_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
    if (isset($_SERVER)) { $_SERVER["argv"] = array(); }
    require_once("/var/www/suitecrm/public/legacy/include/entryPoint.php");
    require_once("/var/www/suitecrm/public/legacy/include/utils.php");
    require_once("/var/www/suitecrm/public/legacy/ModuleInstall/ModuleInstaller.php");

    $modules = array("Contacts"); 
    foreach (glob("/var/www/suitecrm/public/legacy/modules/visp_*", GLOB_ONLYDIR) as $dir) {
        $modules[] = basename($dir);
    }
    $modules = array_unique($modules);

    echo "--- DISCOVERED MODULES FOR EXTENSIONS: " . implode(", ", $modules) . "\n";

    $mi = new ModuleInstaller();
    $mi->modules = $modules;
    $mi->rebuild_extensions();
' >> "$LOG_FILE" 2>&1

log "Compiling Master Logic Hooks Direct From Extensions Source..."
sudo -u www-data php -r '
    define("sugarEntry", true);
    $modules = array("Contacts");
    foreach (glob("/var/www/suitecrm/public/legacy/modules/visp_*", GLOB_ONLYDIR) as $dir) {
        $modules[] = basename($dir);
    }
    $modules = array_unique($modules);

    echo "--- TARGET MODULES FOR HOOK COMPILATION: " . implode(", ", $modules) . "\n";

    foreach ($modules as $mod) {
        $master = "/var/www/suitecrm/public/legacy/custom/modules/" . $mod . "/logic_hooks.php";
        $ext_dir = "/var/www/suitecrm/public/legacy/custom/Extension/modules/" . $mod . "/Ext/LogicHooks";
        $compiled_ext = "/var/www/suitecrm/public/legacy/custom/modules/" . $mod . "/Ext/LogicHooks/logichooks.ext.php";
        
        $hook_array = array(); $hook_version = 1;
        
        if (is_dir($ext_dir)) {
            echo " -> Found extension folder for " . $mod . "\n";
            foreach (glob($ext_dir . "/*.php") as $ext_file) {
                echo "   -> Including extension file: " . basename($ext_file) . "\n";
                include($ext_file);
            }
        }
        
        if (file_exists($compiled_ext)) { 
            include($compiled_ext); 
        }
        
        if (!empty($hook_array)) {
            echo "   -> Writing master logic_hooks.php for " . $mod . "\n";
            if (!is_dir(dirname($master))) {
                mkdir(dirname($master), 0775, true);
            }
            $content = "<?php\n\$hook_version = 1;\n\$hook_array = " . var_export($hook_array, true) . ";\n";
            file_put_contents($master, $content);
        } else {
            echo "   -> No hooks found to write for " . $mod . "\n";
        }
    }
' >> "$LOG_FILE" 2>&1

log "Syncing Core Backend Layout Assets to Frontend..."
cd /var/www/suitecrm || exit 1
sudo -u www-data php bin/console scrm:copy-legacy-assets >> "$LOG_FILE" 2>&1

log "Flushing SuiteCRM 8 Production Container Cache..."
sudo -u www-data php bin/console cache:clear >> "$LOG_FILE" 2>&1

log "Deployment successful for custom files."
exit 0