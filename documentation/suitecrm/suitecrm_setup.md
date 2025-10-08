
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


### Email setup.
After a fresh installation of SuiteCRM you will see a warning message indicationg that you need to setup the system email.

*N.B.* You will need to go to https://myaccount.google.com/apppasswords to generate an app password.