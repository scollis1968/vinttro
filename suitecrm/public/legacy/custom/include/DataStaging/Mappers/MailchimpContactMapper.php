<?php
namespace Custom\DataStaging\Mappers;

use Custom\DataStaging\AbstractStagingMapper;
use BeanFactory;
use $GLOBALS; // For accessing the core logger if needed

class MailchimpContactMapper extends AbstractStagingMapper {
    
    private array $fieldMap = [
        'First Name'    => 'first_name',
        'Last Name'     => 'last_name',
        'Email Address' => 'email1'
    ];

    public function process(array $rawData, \SugarBean $stagingRecord) {
        
        // 1. Validation checks
        if (empty($rawData['Email Address'])) {
            throw new \Exception("Validation Failed: 'Email Address' is missing from raw data.");
        }
        
        // SuiteCRM database layer strictly requires a last name to save a Contact
        if (empty($rawData['Last Name'])) {
            throw new \Exception("Validation Failed: 'Last Name' is empty. SuiteCRM requires this field.");
        }
        
        // 2. Perform Lookup
        $contactId = $this->findContactIdByEmail($rawData['Email Address']);

        if ($contactId) {
            $contact = BeanFactory::getBean('Contacts', $contactId);
            $action = "Updated existing Contact";
        } else {
            $contact = BeanFactory::newBean('Contacts');
            $action = "Created new Contact";
        }
    
        // 3. Custom Phone Routing Logic
        if (!empty($rawData['Phone Number'])) {
            $raw_phone = trim($rawData['Phone Number']);
            
            // Fixed: Changed array notation [] to object property notation ->
            if (preg_match('/^(07|7|\+447)/', $raw_phone)) {
                $contact->phone_mobile = $raw_phone;
                $GLOBALS['log']->info("Staging: Mapped {$raw_phone} to Mobile for " . $rawData['Email Address']);
            } else {
                $contact->phone_work = $raw_phone;
                $GLOBALS['log']->info("Staging: Mapped {$raw_phone} to Work Phone for " . $rawData['Email Address']);
            }
        }

        // 4. Dictionary Mapping Loop
        foreach ($this->fieldMap as $sourceField => $crmField) {
            if (array_key_exists($sourceField, $rawData)) {
                $value = $rawData[$sourceField];

                if (property_exists($contact, $crmField) || isset($contact->field_defs[$crmField])) {
                    $contact->$crmField = $value;
                }
            }
        }
    
        // 5. Save Record Natively
        $contact->save();

        // Fixed: Added missing semicolon to the return statement
        return "{$action} successfully via explicit mapping. (ID: {$contact->id});";
    }
}