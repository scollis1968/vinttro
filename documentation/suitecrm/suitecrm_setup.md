
# SuiteCRM Setup notes..

## Inital Setup
The inital setup of Suite CRM is done via an ansible script found in environments/ansible/roles/suitecrm

To run the script:-
* .N.B this will distroy the whole system and leave you with a clean install of SuiteCRM. 

```
cd ~/git/vinttro/environments/ansible
ansible-playbook -i inventory.ini playbooks/main.yml
``` 


### API setup.
After a fresh installation of SuiteCRM you will get a internal error returned when calling the Api.

Follow these steps: 
```
cd /var/www/suitecrm/Api/V8/OAuth2/
sudo openssl genrsa -out private.key 2048
sudo openssl rsa -in private.key -pubout -out public.key
sudo chmod 600 private.key public.key
sudo chown www-data:www-data p*.key
```

### Image storage
Google cloud storage buckets  n

```
mv /var/www/suitecrm/media /var/www/suitecrm/media.bak
mkdir /var/www/suitecrm/media
chown www-data:www-data /var/www/suitecrm/media
gcsfuse --uid=33 --gid=33 --dir-mode 777 --file-mode 777 -o allow_other visp-uat-private-media /var/www/suitecrm/media
sudo -u www-data rsync -rvP --no-perms --no-owner --no-group /var/www/suitecrm/media.bak/ /var/www/suitecrm/media/

mv /var/www/suitecrm/public/media /var/www/suitecrm/public/media.bak
mkdir /var/www/suitecrm/public/media
chown www-data:www-data /var/www/suitecrm/public/media
sudo gcsfuse --uid=33 --gid=33 --dir-mode 777 --file-mode 777 -o allow_other visp-uat-public-media /var/www/suitecrm/public/media
sudo -u www-data rsync -rvP --no-perms --no-owner --no-group /var/www/suitecrm/public/media.bak/ /var/www/suitecrm/public/media/

```


### Email setup.
After a fresh installation of SuiteCRM you will see a warning message indicationg that you need to setup the system email.

*N.B.* You will need to go to https://myaccount.google.com/apppasswords to generate an app password.

### webhooks.
the web hooks 


