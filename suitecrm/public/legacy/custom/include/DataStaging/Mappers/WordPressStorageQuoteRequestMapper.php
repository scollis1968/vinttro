<?php
namespace Custom\DataStaging\Mappers;

require_once __DIR__ . '/AbstractLeadQuoteStagingMapper.php';

class WordPressStorageQuoteRequestMapper extends AbstractLeadQuoteStagingMapper {

    protected function getLeadSource(): string {
        return 'WordPress Storage Quote';
    }

    protected function getOpportunityName(array $rawData, ?string $vrm): string {
        $coverType = 'Storage Quote';
        $firstName = $rawData['first-name'] ?? '';
        $lastName = $rawData['last-name'] ?? '';
        $fullName = trim("{$firstName} {$lastName}");

        return $vrm ? "{$coverType} - {$vrm} ({$fullName})" : "{$coverType} - {$fullName}";
    }

    protected function mapSpecificLeadFields(\Lead $lead, array $rawData): void {
        if (!empty($rawData['estimated-value'])) {
            $lead->opportunity_amount = $rawData['estimated-value'];
        }

        if (!empty($rawData['notes'])) {
            $lead->description .= "\n\nPayload Notes: " . $rawData['notes'];
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