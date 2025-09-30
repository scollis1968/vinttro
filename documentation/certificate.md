That warning means your website isn't configured for HTTPS, which is a crucial security step. You're right that a Let's Encrypt certificate is an excellent way to enable HTTPS. You'll use a tool called **Certbot** to automate the process of getting and renewing the certificate.

Here's a guide to setting up HTTPS for your sites using Certbot and Nginx.

### **1. Install Certbot and the Nginx Plugin**

First, you need to install Certbot on your Google Cloud VM. You'll use the `apt` package manager for this.

```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx
```

  * `certbot`: The main tool for obtaining and managing Let's Encrypt certificates.
  * `python3-certbot-nginx`: The Nginx plugin for Certbot. This plugin automates the process of configuring Nginx to use the new certificate.

-----

### **2. Configure Nginx for a Challenge**

Certbot needs to verify that you own the domain names you're requesting a certificate for. It does this by creating a temporary file on your web server that it can access from the internet. To allow this, your Nginx virtual host files must be configured to respond to requests on the HTTP protocol.

  * **Check your `wordpress.conf` and `suitecrm.conf` files**: Ensure they have a `server` block that listens on port `80`. Your existing configurations should already have this.

    ```nginx
    server {
        listen 80;
        listen [::]:80;
        server_name wordpress.uat.vinttro.co.uk;
        # ... rest of the config
    }
    ```

-----

### **3. Run Certbot**

Now, run Certbot. The Nginx plugin will automatically handle the certificate request and modify your Nginx configuration files to enable SSL.

```bash
sudo certbot --nginx -d wordpress.uat.vinttro.co.uk -d suitecrm.uat.vinttro.co.uk
```

  * `sudo certbot --nginx`: This tells Certbot to use the Nginx plugin.
  * `-d`: This flag specifies the domain names you want the certificate for. You can include multiple domains in a single command.

Certbot will ask you a few questions:

  * **Email Address**: For urgent renewal and security notices.
  * **Terms of Service**: You must agree to the terms.
  * **Redirection**: Certbot will ask if you want to automatically redirect all HTTP traffic to HTTPS. **Choose this option** as it's a best practice for security.

-----

### **4. Verify the Installation**

After Certbot completes, you should be able to access your websites using HTTPS. The certificate is now installed, and Nginx is configured to serve your sites over a secure connection.

### **5. Automate Certificate Renewal**

Let's Encrypt certificates are only valid for 90 days. Certbot automatically sets up a cron job or a systemd timer to renew the certificates before they expire. You can test the renewal process with this command:

```bash
sudo certbot renew --dry-run
```

This command simulates the renewal process and will tell you if it would have been successful. The `--dry-run` flag is a useful tool for verifying that the automation is set up correctly.

By following these steps, you'll have a fully secure web server for both of your applications.
----
# With Wildcard cert
Yes, you can create a single wildcard certificate for `*.uat.vinttro.co.uk` to cover both `wordpress.uat.vinttro.co.uk` and `suitecrm.uat.vinttro.co.uk`. A wildcard certificate is a more efficient and scalable solution for managing multiple subdomains.

The process is slightly different from a standard certificate request because you'll need to use the **DNS challenge** method to prove domain ownership.

### 1\. The DNS Challenge

To issue a wildcard certificate, Certbot can't use the `http` challenge (which relies on placing a file on the web server) because it doesn't work for wildcard domains. Instead, it uses the **DNS challenge**. This method requires you to create a special **TXT record** in your domain's DNS settings. Certbot will provide the value for this record, and once the record is in place and verified, Certbot will issue the certificate.

### 2\. Run Certbot for a Wildcard Certificate

You'll need to run Certbot with the `--server` and `--manual` flags, and you'll specify the `dns` challenge.

```bash
sudo certbot certonly --server https://acme-v02.api.letsencrypt.org/directory --manual --preferred-challenges dns -d *.uat.vinttro.co.uk -d uat.vinttro.co.uk
```

  * `--manual`: This tells Certbot you'll handle the DNS challenge yourself.
  * `--preferred-challenges dns`: This forces Certbot to use the DNS challenge method.
  * `-d *.uat.vinttro.co.uk`: This requests a certificate for all subdomains.
  * `-d uat.vinttro.co.uk`: You should also include the base domain name in the request.

After running this command, Certbot will pause and provide you with a string. It will instruct you to create a **TXT record** with a specific name (`_acme-challenge.uat.vinttro.co.uk`) and the provided string as the value.

### 3\. Add the TXT Record to Your DNS Provider

Log in to your domain registrar's control panel (e.g., Google Domains, GoDaddy, etc.) and add a new TXT record.

  * **Name**: `_acme-challenge.uat`
  * **Type**: `TXT`
  * **Value**: The string provided by Certbot.

After you've created the record, wait a few minutes for the change to propagate. You can verify that it's working with a DNS lookup tool.

### 4\. Continue with Certbot

Once you've verified the TXT record, go back to your terminal and press Enter to continue. Certbot will check for the TXT record, and if it's found, it will issue the wildcard certificate.

### 5\. Configure Nginx

After Certbot issues the certificate, you'll need to manually configure your Nginx virtual host files to use it. The `certbot --nginx` command modifies the files automatically, but the `--manual` command does not.

In your `nginx` templates (`wordpress.conf.j2` and `suitecrm.conf.j2`), change the `server` block to use the new wildcard certificate files.

```nginx
server {
    listen 443 ssl;
    server_name wordpress.uat.vinttro.co.uk;
    
    ssl_certificate /etc/letsencrypt/live/uat.vinttro.co.uk/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/uat.vinttro.co.uk/privkey.pem;

    # ... rest of your config
}
```

You'll also need to set up a new `server` block to redirect all HTTP traffic to HTTPS.

```nginx
server {
    listen 80;
    server_name wordpress.uat.vinttro.co.uk;
    return 301 https://wordpress.uat.vinttro.co.uk$request_uri;
}
```

This manual process gives you a more flexible and scalable way to manage your subdomains.