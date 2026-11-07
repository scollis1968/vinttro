<?php
namespace Custom\DataStaging\Mappers;

require_once __DIR__ . '/../AbstractStagingMapper.php';
require_once __DIR__ . '/../Services/ContactService.php';

use Custom\DataStaging\AbstractStagingMapper;
use Custom\DataStaging\Services\ContactService;
use BeanFactory;

abstract class AbstractLeadStagingMapper extends AbstractStagingMapper {

    protected ContactService $contactService;

    public function __construct(?ContactService $contactService = null) {
        $this->contactService = $contactService ?? new ContactService();
    }

    public function process(array $rawData, \SugarBean $stagingRecord): string {
        $contact = $this->contactService->findOrCreateContact($rawData);
        $account = $this->resolveAccount($rawData, $contact);

        /** @var \Lead $lead */
        $lead = BeanFactory::newBean('Leads');
        $lead->first_name = $contact->first_name;
        $lead->last_name = $contact->last_name;
        $lead->phone_work = $contact->phone_work;
        $lead->phone_mobile = $contact->phone_mobile;
        $lead->primary_address_postalcode = $contact->primary_address_postalcode;
        $lead->lead_source = $this->getLeadSource();

        $this->mapSpecificLeadFields($lead, $rawData);

        $lead->save();

        if (isset($lead->emailAddress) && !empty($rawData['email'])) {
            $lead->emailAddress->addAddress(trim($rawData['email']), true);
            $lead->emailAddress->save($lead->id, $lead->module_dir);
        }

        if ($lead->load_relationship('contacts')) {
            $lead->contacts->add($contact->id);
        }
        if ($account && $lead->load_relationship('accounts')) {
            $lead->accounts->add($account->id);
        }

        return "Lead '{$lead->name}' created successfully (ID: {$lead->id}) and linked to Contact ID: {$contact->id}";
    }

    protected function resolveAccount(array $rawData, \Contact $contact): ?\Account {
        $companyName = !empty($rawData['company_name']) ? trim($rawData['company_name']) : null;
        if (!$companyName) {
            return null;
        }

        $accountId = $this->findAccountIdByName($companyName);
        /** @var \Account|null $account */
        $account = $accountId ? BeanFactory::retrieveBean('Accounts', $accountId) : null;

        if (empty($account) || empty($account->id)) {
            $account = BeanFactory::newBean('Accounts');
            $account->name = $companyName;
            $account->billing_address_postalcode = $contact->primary_address_postalcode;
            $account->phone_office = $contact->phone_work;
            $account->save();
        }

        if ($contact->load_relationship('accounts')) {
            $contact->accounts->add($account->id);
        }

        return $account;
    }

    abstract protected function getLeadSource(): string;
    abstract protected function mapSpecificLeadFields(\SugarBean $lead, array $rawData): void;
}