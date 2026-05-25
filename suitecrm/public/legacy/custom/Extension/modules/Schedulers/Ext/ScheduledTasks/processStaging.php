<?php
$job_strings[] = 'processStagingRecords';

function processStagingRecords() {
    require_once('include/utils.php');
    require_once('custom/include/DataStaging/StagingMapperInterface.php');
    require_once('custom/include/DataStaging/MapperFactory.php');
    
    $logBean = BeanFactory::newBean('visp_data_staging');
    $pendingList = $logBean->get_full_list('', "visp_data_staging.status = 'pending'", false, 100);

    if (empty($pendingList)) {
        return true; 
    }

    foreach ($pendingList as $record) {
        // Automatically determine if the field is custom (feed_back_c) or standard (feed_back)
        $fbField = isset($record->field_defs['feed_back_c']) ? 'feed_back_c' : 'feed_back';

        try {
            $rawData = json_decode($record->raw_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON formatting in raw_data field.");
            }

            $source = !empty($record->source_system) ? $record->source_system : 'Generic';
            $dataType = !empty($record->data_type) ? $record->data_type : 'Default';

            // 1. Get mapper and execute
            $mapper = \Custom\DataStaging\MapperFactory::getMapper($source, $dataType);
            $resultMessage = $mapper->process($rawData, $record);

            // 2. Log Success Summary
            $record->status = 'processed';
            if (property_exists($record, $fbField) || isset($record->field_defs[$fbField])) {
                $record->$fbField = is_string($resultMessage) ? $resultMessage : 'Processed successfully.';
            }
            $record->save();

        } catch (Exception $e) {
            // 3. Log Failure Details
            $record->status = 'failed';
            if (property_exists($record, $fbField) || isset($record->field_defs[$fbField])) {
                $record->$fbField = "❌ CRASHED: " . $e->getMessage();
            }
            $record->save();
        }
    }

    return true;
}