<?php
namespace Custom\DataStaging\Mappers;

class WordPressStorageQuoteRequestMapper extends AbstractLeadStagingMapper {

    protected function getLeadSource(): string {
        return 'WordPress Storage Quote';
    }

    protected function mapSpecificLeadFields(\SugarBean $lead, array $rawData): void {
        if (!empty($rawData['cover-type'])) {
            $lead->opportunity_type = $rawData['cover-type'];
        }

        if (!empty($rawData['estimated-value'])) {
            $lead->opportunity_amount = $rawData['estimated-value'];
        }

        if (!empty($rawData['notes'])) {
            $lead->description = $rawData['notes'];
        }
    }
}