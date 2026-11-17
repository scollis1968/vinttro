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
        // 1. Resolve Contact
        $contact = $this->contactService->findOrCreateContact($rawData);

        // 2. Resolve Account
        $account = $this->resolveAccount($rawData, $contact);

        // 3. Resolve Opportunity
        list($opportunity, $isNewOpp) = $this->resolveOpportunity($rawData, $contact, $account);

        // 4. Calculate Presentation Rank
        $rank = $isNewOpp ? 1 : $this->getNextPresentationRank($opportunity);

        // 5. Create visp_Lead record
        /** @var \SugarBean $lead */
        $lead = BeanFactory::newBean('visp_lead');
        if (empty($lead)) {
            throw new \RuntimeException("Module 'visp_Lead' could not be loaded via BeanFactory. Ensure it is deployed in Module Builder.");
        }

        // Standard Tracking & Identification
        $leadRef = $rawData['lead_ref'] ?? $rawData['submission_id'] ?? $rawData['form_id'] ?? ('WEB-' . time() . '-' . rand(100, 999));
        $lead->name = (string)$leadRef;
        $lead->supplier_lead_ref_c = (string)$leadRef;
        $lead->supplier_name_c = $rawData['supplier_name'] ?? 'Vinttro Direct';
        $lead->received_at_c = date('Y-m-d H:i:s');
        $lead->presentation_rank_c = $rank;
        $lead->commercial_type_c = $rawData['commercial_type'] ?? 'direct';

        // Attribution & Commercial Logic
        if ($rank === 1) {
            $lead->attribution_pct_c = 100.00;
            $lead->lead_status_c = 'received';
            $lead->commission_status_c = 'pending';
            $lead->description = "Primary credited lead (Rank 1) for Opportunity: {$opportunity->name}";
        } else {
            $lead->attribution_pct_c = 0.00;
            $lead->lead_status_c = 'duplicate_ntu';
            $lead->commission_status_c = 'disqualified';
            $lead->disqualification_reason_c = 'duplicate_window';
            $lead->description = "Secondary lead (Rank {$rank}) for active Opportunity ID: {$opportunity->id}";
        }

        // Apply form-specific overrides from child mapper
        $this->mapSpecificLeadFields($lead, $rawData);
        $lead->save();

        // 6. Establish Relationships
        // Link Opportunity -> visp_Lead
        $this->linkBeans($opportunity, $lead, [
            'opportunities_visp_lead_1',
            'visp_lead_opportunities',
            'opportunities_visp_lead'
        ]);

        // Link Contact -> visp_Lead
        $this->linkBeans($contact, $lead, [
            'contacts_visp_lead_1',
            'visp_lead_contacts',
            'contacts_visp_lead'
        ]);

        // Link Account -> visp_Lead (if defined)
        $this->linkBeans($account, $lead, [
            'accounts_visp_lead_1',
            'visp_lead_accounts',
            'accounts_visp_lead'
        ]);

        // 7. Invalidate Redis front-end summary cache for this contact
        $this->invalidateRedisClientSummary($contact->id);

        $creditStatus = ($rank === 1) ? 'Credited (Rank 1)' : "Uncredited (Rank {$rank})";
        return "visp_Lead ID: {$lead->id} [{$lead->name}] created for Source '{$this->getLeadSource()}' [{$creditStatus}]. Contact: {$contact->id}, Account: {$account->id}, Opportunity: {$opportunity->id}";
    }

    /**
     * Inspects Opportunity to determine next presentation rank
     */
    protected function getNextPresentationRank(\Opportunity $opportunity): int {
        $possibleRels = [
            'opportunities_visp_lead_1',
            'visp_lead_opportunities',
            'opportunities_visp_lead'
        ];

        foreach ($possibleRels as $rel) {
            if ($opportunity->load_relationship($rel)) {
                $related = $opportunity->$rel->get();
                if (is_array($related)) {
                    return count($related) + 1;
                }
            }
        }

        return 1;
    }

    /**
     * Flexible relationship linker that checks both LHS and RHS relationship names
     */
    protected function linkBeans(\SugarBean $beanA, \SugarBean $beanB, array $possibleRelationshipNames): bool {
        foreach ($possibleRelationshipNames as $relName) {
            if ($beanA->load_relationship($relName)) {
                $beanA->$relName->add($beanB->id);
                return true;
            }
            if ($beanB->load_relationship($relName)) {
                $beanB->$relName->add($beanA->id);
                return true;
            }
        }
        return false;
    }

    /**
     * Invalidates Redis cache key so the front end gets fresh data
     */
    protected function invalidateRedisClientSummary(string $contactId): void {
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $host = getenv('REDIS_HOST') ?: 'redis';
                $port = (int)(getenv('REDIS_PORT') ?: 6379);
                if (@$redis->connect($host, $port, 1.0)) {
                    $redis->select(1); // Centralized DB 1
                    $redis->del("client:summary:{$contactId}");
                    $redis->close();
                }
            }
        } catch (\Throwable $e) {
            // Silently skip if Redis connection times out in cron
        }
    }

    protected function resolveAccount(array $rawData, \Contact $contact): \Account {
        $companyName = !empty($rawData['company_name']) ? trim($rawData['company_name']) : null;

        if ($companyName) {
            $accountId = $this->findAccountIdByName($companyName);
            /** @var \Account|null $account */
            $account = $accountId ? BeanFactory::getBean('Accounts', $accountId) : null;

            if (empty($account) || empty($account->id)) {
                $account = BeanFactory::newBean('Accounts');
                $account->name = $companyName;
                $account->account_type = 'Customer';
                $account->billing_address_postalcode = $contact->primary_address_postalcode;
                $account->phone_office = $contact->phone_work;
                $account->save();
            }
        } else {
            $account = $this->findAccountLinkedToContact($contact->id);

            if (empty($account) || empty($account->id)) {
                $accountName = trim("{$contact->first_name} {$contact->last_name}");
                if (empty($accountName)) {
                    $accountName = "Private Client - " . $contact->id;
                }

                $account = BeanFactory::newBean('Accounts');
                $account->name = $accountName;
                $account->account_type = 'Private Client';
                $account->industry = 'None';
                $account->billing_address_postalcode = $contact->primary_address_postalcode;
                $account->phone_office = $contact->phone_mobile ?: $contact->phone_work;
                $account->save();
            }
        }

        if ($contact->load_relationship('accounts')) {
            $contact->accounts->add($account->id);
        }

        return $account;
    }

    protected function resolveOpportunity(array $rawData, \Contact $contact, \Account $account): array {
        $vrm = null;
        if (!empty($rawData['vrm'])) {
            $vrm = strtoupper(trim($rawData['vrm']));
        } elseif (!empty($rawData['vehicle-registration'])) {
            $vrm = strtoupper(trim($rawData['vehicle-registration']));
        }

        $existingOppId = $this->findActiveOpportunityId($contact->id, $vrm);

        if ($existingOppId) {
            /** @var \Opportunity $opportunity */
            $opportunity = BeanFactory::getBean('Opportunities', $existingOppId);
            $isNewOpp = false;
        } else {
            /** @var \Opportunity $opportunity */
            $opportunity = BeanFactory::newBean('Opportunities');
            $opportunity->name = $this->getOpportunityName($rawData, $vrm);
            $opportunity->sales_stage = 'New Quote Request';
            $opportunity->lead_source = $this->getLeadSource();

            if ($vrm && isset($opportunity->field_defs['vehicle_reg_c'])) {
                $opportunity->vehicle_reg_c = $vrm;
            }
            $isNewOpp = true;
        }

        $opportunity->account_id = $account->id;
        $opportunity->account_name = $account->name;

        $this->mapSpecificOpportunityFields($opportunity, $rawData, $isNewOpp);
        $opportunity->save();

        if ($opportunity->load_relationship('contacts')) {
            $opportunity->contacts->add($contact->id);
        }

        return [$opportunity, $isNewOpp];
    }

    private function findAccountLinkedToContact(string $contactId): ?\Account {
        $db = \DBManagerFactory::getInstance();
        $cleanContactId = $db->quote($contactId);

        $query = "SELECT account_id 
                  FROM accounts_contacts 
                  WHERE contact_id = '{$cleanContactId}' 
                    AND deleted = 0 
                  LIMIT 1";

        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            return BeanFactory::getBean('Accounts', $row['account_id']);
        }

        return null;
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
    abstract protected function mapSpecificLeadFields(\SugarBean $lead, array $rawData): void;
    abstract protected function mapSpecificOpportunityFields(\Opportunity $opportunity, array $rawData, bool $isNewOpp): void;
}