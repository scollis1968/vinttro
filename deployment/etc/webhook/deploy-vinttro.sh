#!/bin/bash

# --- Configuration ---
REPO_URL="https://github.com/scollis1968/vinttro.git"
STAGING_DIR="/tmp/vinttro-repo"
LIVE_DIR="/var/www/wordpress"
LOG_FILE="/var/log/vinttro-deploy.log"
TARGET_BRANCH="refs/heads/uat" # <-- SET YOUR REQUIRED BRANCH HERE
BRANCH="uat"

# --- Logging Function ---
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log "--- Deployment triggered for Vinttro  - /etc/webhook/deploy-vinttro.sh ---"


# --- 1. Clone or Pull the Repository ---
if [ ! -d "$STAGING_DIR/.git" ]; then
    # Clone the repository if it doesn't exist
    log "Cloning repository...."
    git clone $REPO_URL $STAGING_DIR
    if [ $? -ne 0 ]; then
        log "ERROR: Git clone failed."
        exit 1
    fi
else
    # Pull latest changes if repository exists
    log "Pulling latest changes..."
    cd $STAGING_DIR
    # Fetch all, then reset to the latest target branch
    git fetch origin
    git reset --hard origin/$BRANCH
    if [ $? -ne 0 ]; then
        log "ERROR: Git pull/reset failed."
        exit 1
    fi
fi

# --- 2. Extract and Deploy Custom WordPress Files (rsync) ---
log "Deploying Vinttro Plugin..."
# Source: /tmp/vinttro-repo/wp-content/plugins/vinttro2.0/
# Destination: /var/www/wordpress/wp-content/plugins/vinttro2.0/
sudo rsync -a $STAGING_DIR/wp-content/plugins/vinttro2.0/ $LIVE_DIR/wp-content/plugins/vinttro2.0/

if [ $? -ne 0 ]; then
    log "ERROR: Plugin rsync failed."
    exit 1
fi

log "Deploying Vinttro Theme..."
# Define the parent directory and the specific folder name
THEME_NAME="vinttro_child_theme"
SOURCE_DIR="$STAGING_DIR/wp-content/themes/$THEME_NAME"
DEST_DIR="/var/www/wordpress/wp-content/themes/$THEME_NAME"

# Ensure the source actually exists before trying to sync
if [ -d "$SOURCE_DIR" ]; then
    # Sync the directory itself (no trailing slash on source) into the parent
    sudo rsync -av "$SOURCE_DIR/" "$DEST_DIR/" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying VINTTRO theme -> rsync -av --delete."
        exit 1
    fi    
    # CRITICAL: Fix permissions so WordPress (www-data) can actually use it
    sudo chown -R www-data:www-data "$DEST_DIR/"
else
    log "ERROR: Source theme directory $SOURCE_DIR not found!"
    exit 1
fi


log "Deploying SuiteCrm/VINTTRO customisation ..."
SOURCE_DIR="/tmp/vinttro-repo/suitecrm/vinttro2.0/"
DESTINATION_DIR="/var/www/suitecrm/vinttro2.0/"
sudo rsync -a $SOURCE_DIR $DESTINATION_DIR
if [ $? -ne 0 ]; then
    log "ERROR: Deploying SuiteCrm/VINTTRO customisation failed."
    exit 1
fi

log "Setting permissions on /var/www/suitecrm/vinttro2.0"
sudo chown -R www-data:www-data /var/www/suitecrm/vinttro2.0
if [ $? -ne 0 ]; then
    log "Error - Setting permissions on /var/www/suitecrm/vinttro2.0"
    exit 1
fi


##-------------------------------------------------------------------------
# VINTTRO  SuiteCRM custom UI
SOURCE_DIR="/tmp/vinttro-repo/suitecrm/public/dist/extensions/vinttro-custom-ui/"
DESTINATION_DIR="/var/www/suitecrm/public/dist/extensions/vinttro-custom-ui/"

# 1. Double check that the source actually exists in the cloned repo
if [ -d "$SOURCE_DIR" ]; then
    
    # FIX: Explicitly enforce parent directory structure creation before running rsync
    sudo mkdir -p "/var/www/suitecrm/public/dist/extensions/"
    sudo chown www-data:www-data "/var/www/suitecrm/public/dist/extensions/"

    # 2. Track rsync errors by appending them to your log file
    sudo rsync -a "$SOURCE_DIR" "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Deploying $DESTINATION_DIR failed during rsync. Check details above."
        exit 1
    fi
else
    log "ERROR: Source UI directory $SOURCE_DIR not found in repository!"
    exit 1
fi

log "Setting permissions on $DESTINATION_DIR"
sudo chown -R www-data:www-data "$DESTINATION_DIR" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "Error - Setting permissions on $DESTINATION_DIR"
    exit 1
fi

##-------------------------------------------------------------------------
# VINTTRO  SuiteCRM  public/legacy (Using Relative Overlay).
    
# 1. Move to the root of your cloned repository
cd /tmp/vinttro-repo
    
# 2. Define the path relative to where you are standing
RELATIVE_SOURCE="suitecrm/public/legacy"
TARGET_ROOT="/var/www"

log "Deploying SuiteCRM custom overlay..."

# 3. Use -aR (archive + relative)
sudo rsync -aR "$RELATIVE_SOURCE" "$TARGET_ROOT/" >> "$LOG_FILE" 2>&1
    
if [ $? -ne 0 ]; then
    log "ERROR: Relative deploy of $RELATIVE_SOURCE failed."
    exit 1
fi

# 4. Fix permissions on the entire newly updated tree
log "Setting permissions on $TARGET_ROOT/$RELATIVE_SOURCE"
sudo chown -R www-data:www-data "$TARGET_ROOT/$RELATIVE_SOURCE" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: Setting permissions on $TARGET_ROOT/$RELATIVE_SOURCE failed."
    exit 1
fi


##-------------------------------------------------------------------------
# --- 3. Automated Post-Deployment Automation & Framework Rebuilds ---
log "Executing automated SuiteCRM Extensions Rebuild..."
sudo -u www-data php -r '
    define("sugarEntry", true);
    $_GET = array(); $_POST = array(); $_REQUEST = array(); $_COOKIE = array();
    if (isset($_SERVER)) { $_SERVER["argv"] = array(); }
    require_once("/var/www/suitecrm/public/legacy/include/entryPoint.php");
    require_once("/var/www/suitecrm/public/legacy/ModuleInstall/ModuleInstaller.php");
    $mi = new ModuleInstaller();
    $mi->modules = array("Contacts");
    $mi->rebuild_extensions();
' >> "$LOG_FILE" 2>&1

log "Rebuilding Master Logic Hooks Layout..."
sudo -u www-data php -r '
    $master = "/var/www/suitecrm/public/legacy/custom/modules/Contacts/logic_hooks.php";
    $ext = "/var/www/suitecrm/public/legacy/custom/modules/Contacts/Ext/LogicHooks/logichooks.ext.php";
    $hook_array = array(); $hook_version = 1;
    if (file_exists($ext)) { include($ext); }
    if (!empty($hook_array)) {
        $content = "<?php\n\$hook_version = 1;\n\$hook_array = " . var_export($hook_array, true) . ";\n";
        file_put_contents($master, $content);
    }
' >> "$LOG_FILE" 2>&1

log "Syncing Core Backend Layout Assets to Frontend..."
cd /var/www/suitecrm
sudo -u www-data php bin/console scrm:copy-legacy-assets >> "$LOG_FILE" 2>&1

log "Flushing SuiteCRM 8 Production Container Cache..."
sudo -u www-data php bin/console cache:clear >> "$LOG_FILE" 2>&1


log "Deployment successful for custom files."
exit 0