<?php
$job_strings[] = 'processStagingRecords';

function processStagingRecords() {
    require_once('include/utils.php');
    require_once('custom/include/DataStaging/StagingMapperInterface.php');
    require_once('custom/include/DataStaging/MapperFactory.php');
    
    $logBean = BeanFactory::newBean('visp_data_staging');
    $pendingList = $logBean->get_full_list('', "visp_data_staging.status = 'pending'", false, 0);




    if (empty($pendingList)) {
        return true; 
    }

    foreach ($pendingList as $record) {
        $fbField = isset($record->field_defs['feed_back_c']) ? 'feed_back_c' : 'feed_back';

        try {
            // 1. Get the raw string from the database
            $rawString = $record->raw_data;

            // 2. Decode standard HTML entities (converts &quot; back to ")
            $cleanJson = html_entity_decode($rawString, ENT_QUOTES, 'UTF-8');

            // 3. Fallback: If SuiteCRM double-encoded or mangled the tokens
            $rawData = json_decode($cleanJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $fallbackJson = str_replace('&amp;', '"', $rawString);
                $rawData = json_decode($fallbackJson, true);
                
                // If the fallback worked, use it
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cleanJson = $fallbackJson;
                } else {
                    // If it STILL fails, throw the official PHP JSON error message
                    throw new Exception("Invalid JSON formatting: " . json_last_error_msg());
                }
            }

            $source = !empty($record->source_system) ? $record->source_system : 'Generic';
            $dataType = !empty($record->data_type) ? $record->data_type : 'Default';

            // 4. Get mapper and execute
            $mapper = \Custom\DataStaging\MapperFactory::getMapper($source, $dataType);
            $resultMessage = $mapper->process($rawData, $record);

            // 5. Log Success Summary
            $record->status = 'processed';
            if (property_exists($record, $fbField) || isset($record->field_defs[$fbField])) {
                $record->$fbField = is_string($resultMessage) ? '✔️ ' . $resultMessage : '✔️ Processed successfully.';
            }
            $record->save();

        } catch (\Throwable $e) { // <-- CHANGED THIS TO \Throwable TO CATCH SYSTEM CRASHES
            // 6. Log Failure Details
            $record->status = 'failed';
            if (property_exists($record, $fbField) || isset($record->field_defs[$fbField])) {
                $record->$fbField = "❌ Error: [" . get_class($e) . "] " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
            }
            $record->save();
        }
    }

    return true;
}