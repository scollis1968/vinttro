The absence of `crontab` confirms that `cron` is not installed in the `chialab/php-dev` image. Schedulers have not run at all on your local development environment, leaving the record in "pending" status.

---

### Step 1: Run the Scheduler Manually Right Now

Execute the SuiteCRM 8 scheduler CLI command to process your pending staging record immediately:

```bash
docker compose exec -u www-data suitecrm php bin/console suitecrm:scheduler:run -v

```

> **Note on SuiteCRM 8 structure:** If `bin/console` returns an error about missing commands or paths, fall back to the legacy cron runner:
> ```bash
> docker compose exec -u www-data suitecrm php -f public/legacy/cron.php
> 
> ```
> 
> 

---

### Step 2: Verify the Staged Record

1. Go back to SuiteCRM in your browser.
2. Navigate to the **Data Staging** module and refresh the page.
3. Check the record status:
* **Processed:** The job executed and handed off the record to lead/quote creation.
* **Failed / Error:** The job encountered an issue while processing the JSON payload.
* **Still Pending:** The custom staging job was not scheduled to run at this interval, or its job URL/method definition needs a check.



To inspect the raw scheduler output if something fails, tail the log:

```bash
docker compose exec suitecrm tail -n 50 public/legacy/suitecrm.log

```

---

### Step 3: Automate It for Local Dev

Because the container does not contain a cron daemon, run it from your host machine rather than modifying the container image:

#### Option A: WSL Host Cron (Runs in background automatically)

Run the command from your WSL host's native cron scheduler:

1. Open WSL crontab:
```bash
crontab -e

```


2. Add this line at the bottom:
```bash
* * * * * cd /home/sc/git/vinttro && docker compose exec -T -u www-data suitecrm php bin/console suitecrm:scheduler:run > /dev/null 2>&1

```


3. Ensure WSL cron is running:
```bash
sudo service cron start

```



#### Option B: Terminal Watcher Loop (For active debugging)

Keep a separate WSL terminal pane open during development that triggers the scheduler every 60 seconds:

```bash
while true; do docker compose exec -u www-data suitecrm php bin/console suitecrm:scheduler:run; sleep 60; done

```