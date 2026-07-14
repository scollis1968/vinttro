<?php
namespace Custom\DataStaging\Mappers;

use Custom\DataStaging\AbstractStagingMapper;
use BeanFactory;

class WordPressCoverQuoteRequestMapper  extends AbstractStagingMapper {
    
    private array $fieldMap = [
        'first-name'    => 'first_name',
        'last-name'     => 'last_name',
        'email' => 'email1',
        'postcode' => 'primary_address_postalcode'
    ];

    public function process(array $rawData, \SugarBean $stagingRecord) {
        
        // 1. Validation checks
        if (empty($rawData['email'])) {
            throw new \Exception("Missing required source field: 'email'.");
        }
        if (empty($rawData['last-name'])) {
            throw new \Exception("CRM requires a 'last-name' to save a contact card.");
        }
        
        // 2. Contact Lookup via your parent abstract class
        $contactId = $this->findContactIdByEmail($rawData['email']);

        if ($contactId) {
            $contact = BeanFactory::getBean('Contacts', $contactId);
            $action = "Updated existing Contact";
        } else {
            $contact = BeanFactory::newBean('Contacts');
            $action = "Created new Contact";
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

                if (property_exists($contact, $crmField) || isset($contact->field_defs[$crmField])) {
                    $contact->$crmField = $value;
                }
            }
        }
    
        // 5. Save the Contact
        $contact->save();

        // This string feeds directly back to the scheduler to populate your feedback field
        return "{$action} successfully. Target CRM ID: {$contact->id}";
    }
}