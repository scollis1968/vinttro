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

        // 2. Resolve or create Account (B2B or B2C Private Client)
        $account = $this->resolveAccount($rawData, $contact);

        // 3. Resolve Opportunity and assign Account
        list($opportunity, $isNewOpp) = $this->resolveOpportunity($rawData, $contact, $account);

        // 4. ALWAYS create a new Lead record for source reporting
        /** @var \Lead $lead */
        $lead = BeanFactory::newBean('Leads');
        $lead->first_name = $contact->first_name;
        $lead->last_name = $contact->last_name;
        $lead->phone_work = $contact->phone_work;
        $lead->phone_mobile = $contact->phone_mobile;
        $lead->primary_address_postalcode = $contact->primary_address_postalcode;
        $lead->lead_source = $this->getLeadSource();

        // Link Account to Lead
        $lead->account_id = $account->id;
        $lead->account_name = $account->name;

        // Indicate credit status for affiliate/source reporting
        if ($isNewOpp) {
            $lead->status = 'New';
            $lead->description = "Primary credited lead for Opportunity: {$opportunity->name}";
        } else {
            $lead->status = 'Recycled';
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

        // 5. Link relationships (Lead -> Contact, Lead -> Opportunity, Lead -> Account)
        if ($lead->load_relationship('contacts')) {
            $lead->contacts->add($contact->id);
        }
        if ($lead->load_relationship('opportunities')) {
            $lead->opportunities->add($opportunity->id);
        }
        if ($lead->load_relationship('accounts')) {
            $lead->accounts->add($account->id);
        }

        $creditStatus = $isNewOpp ? 'Credited (New Opp)' : 'Uncredited (Existing Opp)';
        return "Lead ID: {$lead->id} created for Source '{$this->getLeadSource()}' [{$creditStatus}]. Contact: {$contact->id}, Account: {$account->id}, Opportunity: {$opportunity->id}";
    }

    protected function resolveAccount(array $rawData, \Contact $contact): \Account {
        $companyName = !empty($rawData['company_name']) ? trim($rawData['company_name']) : null;

        if ($companyName) {
            // B2B: Lookup existing Account by name or create a new corporate Account
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
            // B2C / Private Line: Reuse Account already linked to Contact, or create a Private Client Account
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

        // Always ensure Contact is linked to the Account
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
            $opportunity->lead_source = $this->getLeadSource(); // Protected first-touch attribution

            if ($vrm && isset($opportunity->field_defs['vehicle_reg_c'])) {
                $opportunity->vehicle_reg_c = $vrm;
            }
            $isNewOpp = true;
        }

        // Link Account to Opportunity
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
    abstract protected function mapSpecificLeadFields(\Lead $lead, array $rawData): void;
    abstract protected function mapSpecificOpportunityFields(\Opportunity $opportunity, array $rawData, bool $isNewOpp): void;
}