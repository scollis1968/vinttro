# VINTTRO Code deployment

### Overview
This document describe the deployment process for the bespoke VINTTRO code, 


### Git hub triggers
Currently deploying from git hub via a webhook onto the vm, where the vm is running a webhook service that is exposed via the nginx server.
---

### UAT
#### Github Setup

https://github.com/scollis1968/vinttro

![alt text](image.png)

#### VM setup   
Connect to the VM using the brower ssh from [google](https://ssh.cloud.google.com/)

To see what just happend
```
 sudo systemctl status webhook

```
The web hook service is config is found in /etc 
```
root@instance-20250904-100843:/home/stephen_collis# sudo ls -al /etc/webhook
total 20
drwxr-xr-x  2 root    root    4096 Sep 30 15:40 .
drwxr-xr-x 89 root    root    4096 Oct  6 05:06 ..
-rwxr-xr-x  1 webhook webhook 2421 Sep 30 15:40 deploy-vinttro.sh
-rw-r--r--  1 root    root     216 Sep 29 09:17 deploy.sh
-rw-r--r--  1 webhook webhook  635 Sep 30 14:19 hooks.json
```

**/etc/webhook/hooks.json** contains the decleration of the hook. It appears difficult to get github to call the hook only on specific branches,so therefore it becomes the job of the webhook service that receives the request to reject anything that is not for the appropriate branch.

```
[
  {
    "id": "deploy-vinttro",
    "arguments": [
      "{{ . | escapeSingleQuotes }}" 
    ],
    "execute-command": "/etc/webhook/deploy-vinttro.sh",
    "command-working-directory": "/tmp",
    
    "secret": "63f4945d921d599f27ae4fdf5bada3f1",
    "trigger-rule": {
      "and":
      [
        {
           "match":
           {
             "type": "value",
             "value": "refs/heads/uat",
             "parameter":
             {
               "source": "payload",
               "name": "ref"
             }
           }
        }
      ]
    },
    "response-message": "VINTTRO Deployment script triggered."
  }
]
```

** /etc/webhook/deploy-vinttro.sh **
As you can see if the hook satisfies all it's rules then it will execuute the deploy-vinttro.sh cammand that will checkout the appropriate branch and then sync the new files into the appropriate directories.

```
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
# Source: /tmp/vinttro-repo/wp-content/themes/vinttro2.0/
# Destination: /var/www/wordpress/wp-content/themes/vinttro2.0/
sudo rsync -a $STAGING_DIR/wp-content/themes/vinttro2.0/ $LIVE_DIR/wp-content/themes/vinttro2.0/

if [ $? -ne 0 ]; then
    log "ERROR: Theme rsync failed."
    exit 1
fi

log "Deployment successful for custom files."
exit 0

# --- 3. Clean Up / Post-Deployment Tasks ---
# You might want to clear any WP caches here if necessary.
# Example (if you installed wp-cli globally): 
# sudo -u www-data wp cache flush --path=$LIVE_DIR

exit 0
```

---
