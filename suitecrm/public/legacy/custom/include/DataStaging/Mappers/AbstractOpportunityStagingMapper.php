
<?php
namespace Custom\DataStaging\Mappers;

require_once __DIR__ . '/../AbstractStagingMapper.php';
require_once __DIR__ . '/../Services/ContactService.php';

use Custom\DataStaging\AbstractStagingMapper;
use Custom\DataStaging\Services\ContactService;
use BeanFactory;

abstract class AbstractOpportunityStagingMapper extends AbstractStagingMapper {

    protected ContactService $contactService;

    public function __construct(?ContactService $contactService = null) {
        $this->contactService = $contactService ?? new ContactService();
    }

    public function process(array $rawData, \SugarBean $stagingRecord): string {
        // 1. Resolve or create the permanent Contact record
        $contact = $this->contactService->findOrCreateContact($rawData);

        // 2. Resolve or create the Opportunity while protecting lead attribution
        $opportunity = $this->resolveOpportunity($rawData, $contact);

        return "Processed staging record for Contact ID: {$contact->id}, Opportunity ID: {$opportunity->id}";
    }

    protected function resolveOpportunity(array $rawData, \Contact $contact): \Opportunity {
        $vrm = !empty($rawData['vrm']) ? strtoupper(trim($rawData['vrm'])) : null;
        
        // Search for an open Opportunity for this Contact with the same asset/VRM
        $existingOppId = $this->findActiveOpportunityId($contact->id, $vrm);

        if ($existingOppId) {
            /** @var \Opportunity $opportunity */
            $opportunity = BeanFactory::retrieveBean('Opportunities', $existingOppId);
            // DO NOT overwrite $opportunity->lead_source here!
            // First-touch attribution remains intact with the original source.
        } else {
            /** @var \Opportunity $opportunity */
            $opportunity = BeanFactory::newBean('Opportunities');
            $opportunity->name = $this->getOpportunityName($rawData, $vrm);
            $opportunity->sales_stage = 'New Quote Request';
            
            // Set First-Touch attribution
            $opportunity->lead_source = $this->getLeadSource();
            
            if ($vrm) {
                $opportunity->vehicle_reg_c = $vrm;
            }

            $opportunity->save();

            // Link Opportunity to Contact
            if ($opportunity->load_relationship('contacts')) {
                $opportunity->contacts->add($contact->id);
            }
        }

        return $opportunity;
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
            return $row['id'];
        }

        return null;
    }

    abstract protected function getLeadSource(): string;
    abstract protected function getOpportunityName(array $rawData, ?string $vrm): string;
}