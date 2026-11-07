<?php
namespace Custom\DataStaging\Mappers;

require_once __DIR__ . '/AbstractLeadQuoteStagingMapper.php';

class WordPressCoverQuoteRequestMapper extends AbstractLeadQuoteStagingMapper {

    protected function getLeadSource(): string {
        return 'WordPress Cover Quote';
    }

    protected function getOpportunityName(array $rawData, ?string $vrm): string {
        $coverType = !empty($rawData['cover-type']) ? $rawData['cover-type'] : 'Cover Quote';
        $firstName = $rawData['first-name'] ?? '';
        $lastName = $rawData['last-name'] ?? '';
        $fullName = trim("{$firstName} {$lastName}");

        return $vrm ? "{$coverType} - {$vrm} ({$fullName})" : "{$coverType} - {$fullName}";
    }

    /**
     * Map specific fields onto the visp_Lead record
     */
    protected function mapSpecificLeadFields(\SugarBean $lead, array $rawData): void {
        // Vinttro Website is the primary introducer
        $lead->supplier_name_c = 'Vinttro Direct';
        $lead->commercial_type_c = 'direct';

        $notes = [];
        if (!empty($rawData['estimated-value'])) {
            $notes[] = "Estimated Value: £" . $rawData['estimated-value'];
        }
        if (!empty($rawData['cover-type'])) {
            $notes[] = "Requested Cover: " . $rawData['cover-type'];
        }
        if (!empty($rawData['notes'])) {
            $notes[] = "Payload Notes: " . $rawData['notes'];
        }

        if (!empty($notes)) {
            $lead->description = trim($lead->description . "\n\n" . implode("\n", $notes));
        }
    }

    protected function mapSpecificOpportunityFields(\Opportunity $opportunity, array $rawData, bool $isNewOpp): void {
        if (!empty($rawData['estimated-value'])) {
            $opportunity->amount = $rawData['estimated-value'];
        }

        if (!empty($rawData['cover-type'])) {
            $opportunity->opportunity_type = $rawData['cover-type'];
        }

        // Append log if secondary lead submission arrives for open opportunity
        if (!$isNewOpp && !empty($rawData['notes'])) {
            $timestamp = date('Y-m-d H:i');
            $opportunity->description .= "\n\n[{$timestamp} - Secondary Lead ({$this->getLeadSource()})]: " . $rawData['notes'];
        }
    }
}