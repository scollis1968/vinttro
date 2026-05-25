<?php
namespace Custom\DataStaging;

interface StagingMapperInterface {
    /**
     * Process the raw data and map it to SuiteCRM modules.
     * * @param array $rawData The decoded JSON array.
     * @param \SugarBean $stagingRecord The current staging record bean (for logging/linking).
     * @return string|bool Returns true/message on success, or throws an exception on failure.
     */
    public function process(array $rawData, \SugarBean $stagingRecord);
}