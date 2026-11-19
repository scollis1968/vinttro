# Testing redis 

## Seting up a ssh tunnel for use with redis-insight
UAT :-
```
ssh -i ~/.ssh/gcp_redis_key -L 0.0.0.0:6382:127.0.0.1:6379 STEPHEN_COLLIS@34.147.232.155
```

Prod :-
```

gcloud compute ssh prod01-vinttro --zone=europe-west2-a --project=reliable-brace-471109-c4  --troubleshoot

gcloud compute ssh prod01-vinttro --zone=europe-west2-a --project=reliable-brace-471109-c4 -- -L 0.0.0.0:6383:127.0.0.1:6379
```

Unlike UAT—which uses manual VM metadata SSH keys (`STEPHEN_COLLIS`) on local port `6382`—the production VM enforces **GCP OS Login**. OS Login ignores instance metadata keys, maps SSH access to your Google Workspace identity, and assigns an auto-generated Linux username.

## Environment Reference

* **GCP Project:** `visp` (Project ID: `reliable-brace-471109-c4`)
* **VM Instance:** `prod01-vinttro` (`europe-west2-a`)
* **External IP:** `34.39.12.78`
* **OS Login Username:** `stephen_collis_vinttro_co_uk`
* **SSH Key:** `~/.ssh/google_compute_engine`
* **Port Mapping:** Local `0.0.0.0:6383` $\rightarrow$ Remote `127.0.0.1:6379`

## One-Time Setup (From Local WSL)

1. **Confirm active `gcloud` authentication:**
Ensure all commands are run from your local WSL terminal (not from an active SSH session on UAT) and that your Workspace account is active:
```bash
gcloud auth list

```


2. **Retrieve the GCP Project ID:**
`gcloud` commands require the unique Project ID rather than the display name (`visp`):
```bash
gcloud projects list

```


3. **Generate keys, register with OS Login, and verify access:**
Running `gcloud compute ssh` with `--troubleshoot` automatically creates the default `~/.ssh/google_compute_engine` key pair, uploads the public key to your OS Login profile, provisions your POSIX username (`stephen_collis_vinttro_co_uk`), and verifies IAM and firewall rules:
```bash
gcloud compute ssh prod01-vinttro \
  --zone=europe-west2-a \
  --project=reliable-brace-471109-c4 \
  --troubleshoot

```


4. **Confirm your OS Login POSIX username (optional):**
```bash
gcloud compute os-login describe-profile

```



## Starting the Tunnel

Use either command from your local WSL terminal and keep the session open while connected. Point your Redis client to **`127.0.0.1:6383`**.

* **Direct `ssh` (fastest for daily use):**
```bash
ssh -i ~/.ssh/google_compute_engine -L 0.0.0.0:6383:127.0.0.1:6379 stephen_collis_vinttro_co_uk@34.39.12.78

```


* **Via `gcloud` wrapper (automatically refreshes keys/metadata if needed):**
```bash
gcloud compute ssh prod01-vinttro \
  --zone=europe-west2-a \
  --project=reliable-brace-471109-c4 \
  -- -L 0.0.0.0:6383:127.0.0.1:6379

```



If you are working from a network whose public IP is blocked by GCP firewall rules, add `--tunnel-through-iap` before `--` in the `gcloud` command to route the tunnel through Google's Identity-Aware Proxy.