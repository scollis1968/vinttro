<?php
namespace Custom\DataStaging\Mappers;

use Custom\DataStaging\AbstractStagingMapper;
use BeanFactory;

class WordPressCoverQuoteRequestMapper extends AbstractStagingMapper {
    
    // Removed 'email' => 'email1' from here to process it safely via the API below
    private array $fieldMap = [
        'first-name' => 'first_name',
        'last-name'  => 'last_name',
        'postcode'   => 'primary_address_postalcode'
    ];

    public function process(array $rawData, \SugarBean $stagingRecord) {
        
        // 1. Validation checks
        if (empty($rawData['email'])) {
            throw new \Exception("Missing required source field: 'email'.");
        }
        if (empty($rawData['last-name'])) {
            throw new \Exception("CRM requires a 'last-name' to save a contact card.");
        }
        
        // 2. Contact Lookup via safely retrieving the bean
        $contactId = $this->findContactIdByEmail($rawData['email']);
        $contact = null;

        if ($contactId) {
            // retrieveBean returns null safely if the record doesn't exist or is deleted
            $contact = BeanFactory::retrieveBean('Contacts', $contactId);
        }

        if (empty($contact) || empty($contact->id)) {
            $contact = BeanFactory::newBean('Contacts');
            $action = "Created new Contact";
        } else {
            $action = "Updated existing Contact";
        }
    
        // 3. Custom Phone Routing Logic
        if (!empty($rawData['phone'])) {
            $raw_phone = trim($rawData['phone']);
            
            if (preg_match('/^(07|7|\+447)/', $raw_phone)) {
                $contact->phone_mobile = $raw_phone;
            } else {
                $contact->phone_work = $raw_phone;
            }
        }

        // 4. Dictionary Mapping Loop
        foreach ($this->fieldMap as $sourceField => $crmField) {
            if (array_key_exists($sourceField, $rawData)) {
                $value = $rawData[$sourceField];

                // Rely strictly on field_defs since CRM properties are dynamic
                if (isset($contact->field_defs[$crmField])) {
                    $contact->$crmField = $value;
                }
            }
        }

        // 5. Explicitly handle email addressing safely for both updates and inserts
        if (!empty($rawData['email']) && isset($contact->emailAddress)) {
            $contact->emailAddress->addAddress($rawData['email'], true); // 'true' marks it as primary
        }
    
        // 6. Save the Contact
        $contact->save();

        return "{$action} successfully. Target CRM ID: {$contact->id}";
    }
}