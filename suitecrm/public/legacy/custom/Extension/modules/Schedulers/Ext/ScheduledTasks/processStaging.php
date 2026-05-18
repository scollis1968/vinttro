<?php

// The function name MUST be added to the $job_strings array
$job_strings[] = 'processStagingRecords';

function processStagingRecords() {
    require_once('include/utils.php');
    
    // 1. Fetch Pending records from your custom module
    // Replace 'stg_Staging_Logs' with your actual module name
    $logBean = BeanFactory::newBean('visp_data_staging');
    $pendingList = $logBean->get_full_list(
        '', // order by
        "visp_data_staging.status = 'pending'", // where clause
        false, // check_connections
        100 // limit (to avoid timeouts)
    );

    if (empty($pendingList)) {
        return true; 
    }

    foreach ($pendingList as $record) {
        try {
            $rawData = json_decode($record->raw_data, true);
            
            // 2. YOUR MAPPING LOGIC GOES HERE
            // Example: $contact = BeanFactory::newBean('Contacts');
            // $contact->last_name = $rawData['surname'];
            // $contact->save();

            // 3. Update Status
            $record->status = 'processed';
            $record->save();
        } catch (Exception $e) {
            $record->status = 'failed';
            $record->error_log = $e->getMessage();
            $record->save();
        }
    }

    return true;
}