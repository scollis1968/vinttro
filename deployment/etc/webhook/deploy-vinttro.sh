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

log "--- Deployment triggered for Vinttro ---"


# --- 1. Clone or Pull the Repository ---
if [ ! -d "$STAGING_DIR/.git" ]; then
    # Clone the repository if it doesn't exist
    log "Cloning repository..."
    git clone $REPO_URL $STAGING_DIR
    if [ $? -ne 0 ]; then
        log "ERROR: Git clone failed."
        exit 1
    fi
else
    # Pull latest changes if repository exists
    log "Pulling latest changes..."
    cd $STAGING_DIR
    # Fetch all, then reset to the latest main branch (or whatever branch you use)
    git fetch origin
    git reset --hard origin/$BRANCH
    if [ $? -ne 0 ]; then
        log "ERROR: Git pull/reset failed."
        exit 1
    fi
fi

# --- 2. Extract and Deploy Custom WordPress Files (rsync) ---
# We use rsync to efficiently copy ONLY the files within the wp-content directory 
# that are managed by the repo (themes, plugins, uploads placeholders).
# --archive: preserves permissions, ownership, and timestamps.
# --delete: removes files in the destination that are not in the source (for cleanup).
#!/bin/bash
# ... (Configuration, Logging, Git Pull steps remain the same) ...


log "Deploying Vinttro Plugin..."
# Source: /tmp/vinttro-repo/wp-content/plugins/vinttro2.0/
# Destination: /var/www/wordpress/wp-content/plugins/vinttro2.0/
sudo rsync -a $STAGING_DIR/wp-content/plugins/vinttro2.0/ $LIVE_DIR/wp-content/plugins/vinttro2.0/

if [ $? -ne 0 ]; then
    log "ERROR: Plugin rsync failed."
    exit 1
fi

log "Deploying Vinttro Theme..."
SOURCE_DIR="/tmp/vinttro-repo/wp-content/themes/vinttro2.0/"
DESTINATION_DIR="/var/www/wordpress/wp-content/themes/vinttro2.0/"
sudo rsync -a $SOURCE_DIR $DESTINATION_DIR

if [ $? -ne 0 ]; then
    log "ERROR: Theme rsync failed."
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

log "Deployment successful for custom files."
exit 0

# --- 3. Clean Up / Post-Deployment Tasks ---
# You might want to clear any WP caches here if necessary.
# Example (if you installed wp-cli globally): 
# sudo -u www-data wp cache flush --path=$LIVE_DIR

exit 0