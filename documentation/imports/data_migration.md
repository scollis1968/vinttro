# Data Migration

This docuemnet records the process we need to go through to export the data from various active legacy systems into one place.


## Active Systems 
Active systems are systems that will be used in the forseable future and therfore will need a continual and preferably automated sysnc process.

1. Acturis
1. Google - Diary


## Legacy or Retiering Systems 
For legacy or retiring systems it is acceptable to have manual processes to migrate data. Not too much time should be spent on automation as these systems will have a cutoff data when they be come read only.

1. Mondays
1. MailChimp

| Source Field	              | SuiteCRM Destination      | Status |
|-----------------------------|---------------------------|:------:|
| First Name		          | contact.first_name        | 👌     |
| Last Name		              | conatct.last_name         | 👌     |
| Email Address	              | conatct.email1            | 👌     |	
| Phone Number		          | contact.  
| Address		
| Car Insurance Renewal Date		
| Active		
| Membership ID		
| Username		
| Cars Owned		
| Bio		
| Birthday		
| User ID		
| Membership Plan		
| Terms Agreed		
| Referrer		
| Postcode		
| EMAIL_TYPE		
| MEMBER_RATING		
| OPTIN_TIME		
| OPTIN_IP		
| CONFIRM_TIME		
| CONFIRM_IP		
| GMTOFF		
| DSTOFF		
| TIMEZONE		
| CC		
| REGION		
| LAST_CHANGED		
| LEID		
| EUID		
| NOTES		
| TAGS		
````
	
````
cd /var/www/suitecrm/vinttro2.0/
sudo -u www-data php import_mailchimp.php /mnt/vinttro_data_import/mailchimp/mailchimp_contacts_10.csv
````

1

## Acturis - Data 

From inside Acturis reports can only be sheduled to run to an Actuiris account, their dosent appear to be any way to autmate the export.

when you export a report from inside the ACTURIS ui it breaks out into a browser and presents a sceen to select various formating options and a "run" button that makes a post to the backend.

TODO - it may be work looking to see if the export part could be reverese engineered.

### Client Records
The "Advanced client report" :-
````
cd /var/www/suitecrm/vinttro2.0/
php import_acturis_advanced_client_report.php /path/to/your/acturis_data.csv
sudo -u www-data php /var/www/suitecrm/vinttro2.0/import_acturis_advanced_client_report.php /tmp/acturis_report.csv

````

### Policy Data
### Renewal Data
### Events 