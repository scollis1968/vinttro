<?php
// The function name MUST be added to the $job_strings array
$job_strings[] = 'processStagingRecords';

function processStagingRecords() {
    require_once('include/utils.php');
    // Include our Factory and Interface base files
    require_once('custom/include/DataStaging/StagingMapperInterface.php');
    require_once('custom/include/DataStaging/MapperFactory.php');
    
    $logBean = BeanFactory::newBean('visp_data_staging');
    $pendingList = $logBean->get_full_list(
        '', 
        "visp_data_staging.status = 'pending'", 
        false, 
        100 // Safe processing batch chunk
    );

    if (empty($pendingList)) {
        return true; 
    }

    foreach ($pendingList as $record) {
        try {
            $rawData = json_decode($record->raw_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON data structure in raw_data field.");
            }

            // Fallback defaults if fields are empty
            $source = !empty($record->source_system) ? $record->source_system : 'Generic';
            $dataType = !empty($record->data_type) ? $record->data_type : 'Contact';

            // 1. Ask the Factory for the specific mapping processor
            $mapper = \Custom\DataStaging\MapperFactory::getMapper($source, $dataType);
            
            // 2. Execute the mapping processor
            $resultMessage = $mapper->process($rawData, $record);

            // 3. Update Status on Success
            $record->status = 'processed';
            // Store the success message or created record ID in your error log or a note field if desired
            $record->error_log = is_string($resultMessage) ? $resultMessage : 'Processed successfully';
            $record->save();

        } catch (Exception $e) {
            // Any validation failures or mapping crashes land here cleanly
            $record->status = 'failed';
            $record->error_log = $e->getMessage();
            $record->save();
        }
    }

    return true;
}