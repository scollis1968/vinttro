# Infrastructure

## Overview.

### Current setup (September 2025)
The current setup consists of the following components.
1. Doddle website
    * Supported by Simple click
    * Maintained / Updated my Simon.
1. Monday.com
    * Maintained by Dave H.
1. Mailchimp
1. google mail
1. Xero
1. Acturis


### Chalenges / Requirements

### Proposed setup.


#### Phase 1
Wordpress and SuiteCRM Deployed to a Google cloud vm using an IAC script.

1. Build deployment script for UAT enviorment in Google cloud. 

-----

You can set up SuiteCRM and WordPress on a Google Cloud Ubuntu VM using Infrastructure as Code (IaC) with tools like **Terraform** and **Ansible**. This approach allows you to automate the provisioning of the VM and the installation of the software, making the process repeatable and well-documented.

### 1\. The IaC Approach

**Infrastructure as Code (IaC)** manages and provisions computing infrastructure using code and software development practices, such as version control. This is a significant improvement over manual configuration, as it ensures consistency, reduces human error, and makes the entire setup repeatable.

  * **Terraform**: This tool is used for **infrastructure provisioning**. It will define and create the Google Cloud VM instance, the necessary networking rules (firewalls), and other cloud resources. You'll define the "what" of your infrastructure (e.g., "I need a VM with this much RAM and a specific OS image").
  * **Ansible**: This tool is used for **configuration management** and **application deployment**. Once the VM is created by Terraform, Ansible will connect to it and perform the software installation steps. You'll define the "how" (e.g., "install Apache, PHP, MySQL, and then download and configure SuiteCRM and WordPress").

This two-step process separates the concerns of cloud resource creation from the application-level setup, which is a best practice in modern DevOps.

-----

### 2\. Step-by-Step Documentation

This is a high-level overview of the process. Each step will involve creating and modifying specific configuration files.

#### **Step 1: Prerequisites** \* **Google Cloud Account:** You'll need a project with billing enabled.

  * **Service Account:** Create a service account with the appropriate permissions (e.g., Compute Admin, Service Account User) to provision resources in your project. Generate a JSON key for this account.
  * **Local Machine Setup:** Install the `gcloud CLI`, `Terraform`, and `Ansible`.

#### **Step 2: Terraform for Infrastructure Provisioning**

1.  **Project Structure:** Create a new directory for your project. Inside, create a `main.tf` file.

2.  **Provider Configuration:** In `main.tf`, configure the Google Cloud provider. You'll specify your project ID, a region, and the path to your service account key file.

    ```terraform
    terraform {
      required_providers {
        google = {
          source = "hashicorp/google"
          version = ">= 4.0"
        }
      }
    }

    provider "google" {
      project      = "your-gcp-project-id"
      region       = "your-preferred-region"
      credentials  = file("path/to/your/service-account-key.json")
    }
    ```

3.  **VM Instance and Network:** Define the VM instance, including the machine type and the Ubuntu image. Also, create a firewall rule to allow HTTP and HTTPS traffic so your web applications are accessible.

    ```terraform
    # Create a new Google Compute Engine instance
    resource "google_compute_instance" "vm_instance" {
      name         = "suitecrm-wordpress-vm"
      machine_type = "e2-medium"
      zone         = "your-preferred-zone"

      boot_disk {
        initialize_params {
          image = "ubuntu-os-cloud/ubuntu-2204-lts"
        }
      }

      network_interface {
        network = "default"
        access_config {
          # External IP for SSH and web access
        }
      }

      # Provisioning with Ansible
      provisioner "remote-exec" {
        inline = [
          "sudo apt-get update -y",
          "sudo apt-get install -y python3-pip",
          "pip3 install ansible"
        ]
      }
    }

    # Allow HTTP and HTTPS traffic
    resource "google_compute_firewall" "allow_web" {
      name    = "allow-http-https"
      network = "default"
      allow {
        protocol = "tcp"
        ports    = ["80", "443"]
      }
      source_ranges = ["0.0.0.0/0"]
    }
    ```

4.  **Terraform Commands:** Run these commands from your terminal:

      * `terraform init`: Initializes the project and downloads the necessary providers.
      * `terraform plan`: Shows you what resources will be created.
      * `terraform apply`: Provisions the VM and firewall rules in your Google Cloud project.

#### **Step 3: Ansible for Configuration Management**

1.  **Ansible Directory:** In your project folder, create a new directory (e.g., `ansible`) to store your Ansible playbook and related files.

2.  **Inventory File:** Create an `inventory.ini` file to tell Ansible where to run the commands. Use the external IP of the VM created by Terraform.

    ```ini
    [webservers]
    <VM_EXTERNAL_IP>
    ```

3.  **Ansible Playbook:** Create a `playbook.yml` file. This is the heart of your automation. It will:

      * Install the **LAMP** stack (Linux, Apache, MySQL, PHP).
      * Download and unzip the SuiteCRM and WordPress files.
      * Create separate MySQL databases and users for each application.
      * Configure Apache virtual hosts for SuiteCRM and WordPress.
      * Set up file permissions and other necessary configurations.
      * This is a simplified example; a full playbook would be much more detailed, but it demonstrates the structure.

    <!-- end list -->

    ```yaml
    ---
    - name: Configure LAMP stack and deploy applications
      hosts: webservers
      become: yes
      tasks:
        - name: Install LAMP stack components
          ansible.builtin.apt:
            name:
              - apache2
              - mariadb-server
              - php
              - php-mysql
              - php-curl
              - php-json
              - php-mbstring
              - php-xml
              - php-zip
              - unzip
            state: present
        
        # ... (Additional tasks for MySQL setup, creating databases, and users)
        
        - name: Download and unzip WordPress
          ansible.builtin.unarchive:
            src: https://wordpress.org/latest.tar.gz
            dest: /var/www/html/
            remote_src: yes
            creates: /var/www/html/wordpress

        - name: Download and unzip SuiteCRM
          ansible.builtin.unarchive:
            src: https://suitecrm.com/files/162/SuiteCRM-7.11/525/SuiteCRM-7.11.18.zip # Use the latest version
            dest: /var/www/html/
            remote_src: yes
            creates: /var/www/html/SuiteCRM

        # ... (Additional tasks for configuring WordPress and SuiteCRM, setting permissions, and creating virtual hosts)
    ```

4.  **Execute the Playbook:** Run the Ansible playbook from your terminal, pointing to your inventory file.

    ```bash
    ansible-playbook -i ansible/inventory.ini ansible/playbook.yml --private-key=~/.ssh/your-ssh-key
    ```

    Replace `your-ssh-key` with the path to the SSH key you use to connect to the VM.

-----

### 3\. Finalizing and Repeating the Process

After the Ansible playbook finishes, your VM will have both WordPress and SuiteCRM installed. You will still need to perform the final **web-based installation steps** by navigating to the IP address of your VM in a browser (e.g., `http://<VM_EXTERNAL_IP>/wordpress` and `http://<VM_EXTERNAL_IP>/SuiteCRM`).

To repeat this process, you simply need to:

1.  Modify your Terraform variables (e.g., project ID).
2.  Run `terraform apply` to create a new, identical VM.
3.  Update the `inventory.ini` file with the new VM's external IP address.
4.  Run `ansible-playbook` to automatically configure the new VM.

This IaC approach ensures that your setup is reproducible, consistent, and documented in code.

---
Of course. Now that you've solved the authentication issue, here's the Ansible syntax for setting up the MySQL databases for both WordPress and SuiteCRM. You'll use the **`community.mysql`** collection, which provides dedicated modules for managing MySQL and MariaDB.

### Step 1: Install the `community.mysql` Collection

First, ensure you have the necessary Ansible collection and Python libraries. The `community.mysql` collection isn't part of the default Ansible package. Run this command on your control node (the machine running Ansible):

```bash
ansible-galaxy collection install community.mysql
pip install PyMySQL
```

This installs the collection and the required Python library to talk to your MySQL/MariaDB server.

-----

### Step 2: Define Variables

It's best practice to define your sensitive information as variables. This keeps your playbook clean and secure. You can place these in a `vars` file (e.g., `vars/main.yml`) or directly in your playbook.

```yaml
# vars/main.yml
mysql_root_password: "your_mysql_root_password"
wordpress_db_name: "wordpress_db"
wordpress_db_user: "wordpress_user"
wordpress_db_password: "wordpress_password"
suitecrm_db_name: "suitecrm_db"
suitecrm_db_user: "suitecrm_user"
suitecrm_db_password: "suitecrm_password"
```

-----

### Step 3: Write the Playbook

This playbook will connect to your database server, create the necessary databases and users, and grant the correct permissions. Make sure your inventory file points to the correct database server.

```yaml
---
- name: Setup MySQL databases for WordPress and SuiteCRM
  hosts: your_database_server_group
  become: yes
  vars_files:
    - vars/main.yml

  tasks:
    - name: Create WordPress database
      community.mysql.mysql_db:
        name: "{{ wordpress_db_name }}"
        state: present
        login_user: root
        login_password: "{{ mysql_root_password }}"

    - name: Create WordPress user and grant permissions
      community.mysql.mysql_user:
        name: "{{ wordpress_db_user }}"
        password: "{{ wordpress_db_password }}"
        host: "%"
        priv: "{{ wordpress_db_name }}.*:ALL"
        state: present
        login_user: root
        login_password: "{{ mysql_root_password }}"

    - name: Create SuiteCRM database
      community.mysql.mysql_db:
        name: "{{ suitecrm_db_name }}"
        state: present
        login_user: root
        login_password: "{{ mysql_root_password }}"

    - name: Create SuiteCRM user and grant permissions
      community.mysql.mysql_user:
        name: "{{ suitecrm_db_user }}"
        password: "{{ suitecrm_db_password }}"
        host: "%"
        priv: "{{ suitecrm_db_name }}.*:ALL"
        state: present
        login_user: root
        login_password: "{{ mysql_root_password }}"

```

#### Explanation of Key Parameters:

  * **`hosts`**: This should be the name of the group in your inventory file that contains your database server.
  * **`become: yes`**: This allows the Ansible playbook to run tasks with elevated privileges (like `sudo`), which is often necessary for managing system services or files.
  * **`vars_files`**: This line tells Ansible to load variables from the `vars/main.yml` file.
  * **`community.mysql.mysql_db`**: This module manages the presence or absence of a database.
  * **`community.mysql.mysql_user`**: This module manages the creation and privileges of a MySQL user.
  * **`login_user` & `login_password`**: These are the credentials Ansible will use to authenticate with the MySQL server to perform the tasks. Using `root` with the root password is a common practice for this purpose.
  * **`host: "%"`**: This is crucial. It allows the new user to connect from **any host**, which is necessary for the web server to connect to the database. If your web server and database server are on the same machine, you can change this to `localhost`.
  * **`priv: "{{ db_name }}.*:ALL"`**: This grants the user all privileges (`ALL`) on all tables (`*`) within the specified database (`{{ db_name }}`). This is the required access for both WordPress and SuiteCRM to function.

To run this playbook, use the following command:

```bash
ansible-playbook -i inventory.ini your_playbook_name.yml
```