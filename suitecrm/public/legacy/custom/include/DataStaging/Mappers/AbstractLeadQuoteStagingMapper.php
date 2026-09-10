<?php
namespace Custom\DataStaging\Mappers;

require_once __DIR__ . '/../AbstractStagingMapper.php';
require_once __DIR__ . '/../Services/ContactService.php';

use Custom\DataStaging\AbstractStagingMapper;
use Custom\DataStaging\Services\ContactService;
use BeanFactory;

abstract class AbstractLeadQuoteStagingMapper extends AbstractStagingMapper {

    protected ContactService $contactService;

    public function __construct(?ContactService $contactService = null) {
        $this->contactService = $contactService ?? new ContactService();
    }

    public function process(array $rawData, \SugarBean $stagingRecord): string {
        // 1. Resolve or create Contact
        $contact = $this->contactService->findOrCreateContact($rawData);

        // 2. Resolve Opportunity (returns [Opportunity $opp, bool $isNewOpp])
        list($opportunity, $isNewOpp) = $this->resolveOpportunity($rawData, $contact);

        // 3. ALWAYS create a new Lead record for source reporting
        /** @var \Lead $lead */
        $lead = BeanFactory::newBean('Leads');
        $lead->first_name = $contact->first_name;
        $lead->last_name = $contact->last_name;
        $lead->phone_work = $contact->phone_work;
        $lead->phone_mobile = $contact->phone_mobile;
        $lead->primary_address_postalcode = $contact->primary_address_postalcode;
        $lead->lead_source = $this->getLeadSource();

        // Indicate credit status for affiliate/source reporting
        if ($isNewOpp) {
            $lead->status = 'New';
            $lead->description = "Primary credited lead for Opportunity: {$opportunity->name}";
        } else {
            $lead->status = 'Recycled'; // Or custom status like 'Uncredited Duplicate'
            $lead->description = "Secondary lead submission linked to existing active Opportunity ID: {$opportunity->id}";
        }

        // Map quote-specific fields onto Lead
        $this->mapSpecificLeadFields($lead, $rawData);

        $lead->save();

        // Primary email on Lead
        if (isset($lead->emailAddress) && !empty($rawData['email'])) {
            $lead->emailAddress->addAddress(trim($rawData['email']), true);
            $lead->emailAddress->save($lead->id, $lead->module_dir);
        }

        // 4. Link relationships (Lead -> Contact & Lead -> Opportunity)
        if ($lead->load_relationship('contacts')) {
            $lead->contacts->add($contact->id);
        }
        if ($lead->load_relationship('opportunities')) {
            $lead->opportunities->add($opportunity->id);
        }

        $creditStatus = $isNewOpp ? 'Credited (New Opp)' : 'Uncredited (Existing Opp)';
        return "Lead ID: {$lead->id} created for Source '{$this->getLeadSource()}' [{$creditStatus}]. Contact: {$contact->id}, Opportunity: {$opportunity->id}";
    }

    protected function resolveOpportunity(array $rawData, \Contact $contact): array {
        $vrm = null;
        if (!empty($rawData['vrm'])) {
            $vrm = strtoupper(trim($rawData['vrm']));
        } elseif (!empty($rawData['vehicle-registration'])) {
            $vrm = strtoupper(trim($rawData['vehicle-registration']));
        }

        $existingOppId = $this->findActiveOpportunityId($contact->id, $vrm);

        if ($existingOppId) {
            /** @var \Opportunity $opportunity */
            $opportunity = BeanFactory::retrieveBean('Opportunities', $existingOppId);
            $isNewOpp = false;
        } else {
            /** @var \Opportunity $opportunity */
            $opportunity = BeanFactory::newBean('Opportunities');
            $opportunity->name = $this->getOpportunityName($rawData, $vrm);
            $opportunity->sales_stage = 'New Quote Request';
            $opportunity->lead_source = $this->getLeadSource(); // Protected first-touch attribution

            if ($vrm && isset($opportunity->field_defs['vehicle_reg_c'])) {
                $opportunity->vehicle_reg_c = $vrm;
            }
            $isNewOpp = true;
        }

        $this->mapSpecificOpportunityFields($opportunity, $rawData, $isNewOpp);
        $opportunity->save();

        if ($opportunity->load_relationship('contacts')) {
            $opportunity->contacts->add($contact->id);
        }

        return [$opportunity, $isNewOpp];
    }

    private function findActiveOpportunityId(string $contactId, ?string $vrm): ?string {
        $db = \DBManagerFactory::getInstance();
        $cleanContactId = $db->quote($contactId);

        $vrmWhere = '';
        if ($vrm) {
            $cleanVrm = $db->quote($vrm);
            $vrmWhere = "AND o_cstm.vehicle_reg_c = '{$cleanVrm}'";
        }

        $query = "SELECT o.id 
                  FROM opportunities o
                  INNER JOIN opportunities_contacts oc ON oc.opportunity_id = o.id AND oc.deleted = 0
                  LEFT JOIN opportunities_cstm o_cstm ON o_cstm.id_c = o.id
                  WHERE oc.contact_id = '{$cleanContactId}'
                    AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                    AND o.deleted = 0
                    {$vrmWhere}
                  ORDER BY o.date_entered DESC
                  LIMIT 1";

        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            return $row['bean_id'] ?? $row['id'];
        }

        return null;
    }

    abstract protected function getLeadSource(): string;
    abstract protected function getOpportunityName(array $rawData, ?string $vrm): string;
    abstract protected function mapSpecificLeadFields(\Lead $lead, array $rawData): void;
    abstract protected function mapSpecificOpportunityFields(\Opportunity $opportunity, array $rawData, bool $isNewOpp): void;
}