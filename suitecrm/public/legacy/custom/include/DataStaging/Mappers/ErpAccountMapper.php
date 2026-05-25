<?php
namespace Custom\DataStaging\Mappers;

use Custom\DataStaging\StagingMapperInterface;
use BeanFactory;

class ErpAccountMapper implements StagingMapperInterface {
    public function process(array $rawData, \SugarBean $stagingRecord) {
        // 1. Validation check
        if (empty($rawData['company_name'])) {
            throw new \Exception("Validation Failed: 'company_name' is missing from raw data.");
        }

        // 2. Map to SuiteCRM Bean
        $account = BeanFactory::newBean('Accounts');
        
        // Example handling of duplicate checks
        // (You could write a query to see if the account already exists via an external ID)
        
        $account->name = $rawData['company_name'];
        $account->phone_office = $rawData['main_phone'] ?? '';
        $account->billing_address_street = $rawData['address_line_1'] ?? '';
        
        $account->save();

        // Optional: Link the newly created account ID back to your staging record for auditing
        // $stagingRecord->account_id_c = $account->id; 

        return "Successfully created Account ID: " . $account->id;
    }
}