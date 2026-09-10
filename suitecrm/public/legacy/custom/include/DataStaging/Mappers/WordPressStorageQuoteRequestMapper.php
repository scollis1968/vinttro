<?php
namespace Custom\DataStaging\Mappers;

require_once 'custom/include/DataStaging/Mappers/AbstractLeadStagingMapper.php';

class WordPressStorageQuoteRequestMapper extends AbstractLeadStagingMapper {

    protected function getLeadSource(): string {
        return 'WordPress Storage Quote';
    }

    protected function mapSpecificLeadFields(\SugarBean $lead, array $rawData): void {
        if (!empty($rawData['cover-type'])) {
            $lead->opportunity_type = 'Storage Quote' ;
        }

        if (!empty($rawData['estimated-value'])) {
            $lead->opportunity_amount = $rawData['estimated-value'];
        }

        if (!empty($rawData['notes'])) {
            $lead->description = $rawData['notes'];
        }
    }
}